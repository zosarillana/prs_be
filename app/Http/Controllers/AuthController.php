<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserLoginHistory;
use Auth;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => ['required', 'confirmed', Password::defaults()],
            'department' => 'nullable|array',
            'role' => 'nullable|array',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'department' => $validated['department'] ?? [],
            'role' => $validated['role'] ?? [],
        ]);

        // ✅ Audit the registration event
        // auth()->id() will likely be null because no one is logged in yet,
        // but we still capture the IP, user agent, and new user data.
        auditLog('registered_user', $user, null, $user->only(['id', 'name', 'email', 'department', 'role']));

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $user->only(['id', 'name', 'email', 'department', 'role']),
        ]);
    }

    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (! Auth::attempt($credentials)) {
            return response()->json([
                'message' => 'Invalid credentials',
            ], 401);
        }

        $user = User::where('email', $request->email)->first();
        $token = $user->createToken('auth_token')->plainTextToken;

        // ✅ Record login history
        UserLoginHistory::create([
            'user_id' => $user->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'logged_in_at' => now(),
        ]);

        // ✅ Audit log the login event
        auditLog('user_login', $user, null, [
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $user->only([
                'id', 'name', 'email', 'department', 'role', 'signature', 'created_at', 'updated_at',
            ]),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();

        // 🔹 If using API token (Personal Access Token)
        $token = $user?->currentAccessToken();
        if ($token && ! ($token instanceof \Laravel\Sanctum\TransientToken)) {
            $token->delete();
        }

        // 🔹 Update logout time for latest login history
        if ($user) {
            UserLoginHistory::where('user_id', $user->id)
                ->orderByDesc('id')
                ->first()
                ?->update(['logged_out_at' => now()]);
        }

        // 🔹 Audit log
        if (function_exists('auditLog')) {
            auditLog('user_logout', $user, null, [
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        }

        // 🔹 Invalidate session if using Sanctum + cookies
        if ($request->hasSession()) {
            auth()->guard('web')->logout(); // ensure session logout
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->json([
            'message' => 'Logged out successfully',
        ]);
    }

    public function changePassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'current_password' => 'required',
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = $request->user();

        // Verify current password
        if (! Hash::check($validated['current_password'], $user->password)) {
            return response()->json([
                'message' => 'Current password is incorrect',
            ], 422);
        }

        // 👉 Capture old values before update (without showing actual password)
        $oldValues = ['password_changed' => false];

        // Update password
        $user->password = Hash::make($validated['password']);
        $user->save();

        // 👉 Audit log the password change
        auditLog('changed_password', $user, $oldValues, ['password_changed' => true]);

        return response()->json([
            'message' => 'Password changed successfully',
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => $request->user()->only(['id', 'name', 'email', 'department', 'role']),
        ]);
    }
}
