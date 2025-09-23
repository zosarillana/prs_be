<?php

namespace App\Http\Controllers;

use App\Helpers\MapUsers;
use App\Service\Paginator\PaginatorService;
use App\Service\User\UserService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Intervention\Image\Laravel\Facades\Image;

class UserController extends Controller
{
    protected $userService;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }

    /**
     * Paginated list of users.
     */
    public function index(Request $request, PaginatorService $paginator)
    {
        // Build query using UserService
        $query = $this->userService->getQuery([
            'searchTerm' => $request->input('searchTerm'),
            'sortBy' => $request->input('sortBy', 'id'),
            'sortOrder' => $request->input('sortOrder', 'asc'),
        ]);

        // Paginate results
        $result = $paginator->paginate(
            $query,
            $request->input('pageNumber', 1),
            $request->input('pageSize', 10)
        );

        // Optionally map/transform each user (like MapPurchaseReport)
        $result['items'] = collect($result['items'])
            ->map(fn ($user) => MapUsers::mapTable($user))
            ->toArray();

        return response()->json($result);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6|confirmed',
        ]);

        $user = $this->userService->createUser($validated);

        // 👉 Add audit log
        auditLog('created_user', $user, null, $user->toArray());

        return response()->json($user, 201);
    }

    public function show(string $id)
    {
        $user = $this->userService->getUserById($id);

        if (! $user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        return response()->json($user, 200);
    }

    public function update(Request $request, string $id)
    {
        $user = $this->userService->getUserById($id);
        if (! $user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => ['sometimes', 'email', Rule::unique('users')->ignore($user->id)],
            'password' => ['sometimes', 'confirmed', Password::defaults()], // Updated validation
            'department' => 'sometimes|array',
            'role' => 'sometimes|array',
        ]);

        $file = $request->file('signature');
        if ($file) {
            $validated['signature'] = $file;
        }

        // 👉 Capture old values before update
        $oldValues = $user->toArray();

        // Remove password from oldValues for security
        unset($oldValues['password']);

        $user = $this->userService->updateUser($user, $validated);

        // 👉 Log only the changed fields (exclude password from changes log)
        $changes = array_intersect_key($user->getChanges(), $validated);
        if (isset($changes['password'])) {
            $changes['password'] = '[UPDATED]'; // Don't log actual password
        }

        auditLog('updated_user', $user, $oldValues, $changes);

        return response()->json($user, 200);
    }

    public function updateSignature(Request $request, string $id)
    {
        $user = $this->userService->getUserById($id);
        if (! $user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        $file = $request->file('signature');
        if ($file && $file->isValid()) {
            $oldValues = $user->only('signature'); // old signature path

            $filename = 'signature_'.$id.'_'.time().'.png';
            $image = Image::read($file)
                ->resize(577, 433)
                ->toPng(false);
            $path = 'signatures/'.$filename;
            Storage::disk('public')->put($path, $image);

            $user->signature = Storage::url($path);
            $user->save();

            // 👉 Audit the signature update
            auditLog('updated_user_signature', $user, $oldValues, $user->only('signature'));

            return response()->json($user, 200);
        }

        return response()->json(['message' => 'No signature file uploaded'], 400);
    }

    public function destroy(string $id)
    {
        $user = $this->userService->getUserById($id);

        if (! $user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        // 👉 Capture old values before delete
        $oldValues = $user->toArray();

        $this->userService->deleteUser($user);

        // 👉 Log deletion
        auditLog('deleted_user', $user, $oldValues, null);

        return response()->json(['message' => 'User deleted successfully'], 200);
    }

    public function updatePassword(Request $request, string $id)
    {
        $user = $this->userService->getUserById($id);
        if (! $user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        $validated = $request->validate([
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        // 👉 Capture old values before update (without showing actual password)
        $oldValues = ['password_updated' => false];

        // Update password using UserService or directly
        $user->password = Hash::make($validated['password']);
        $user->save();

        // 👉 Log the password update
        auditLog('updated_user_password', $user, $oldValues, ['password_updated' => true]);

        return response()->json([
            'message' => 'Password updated successfully',
        ], 200);
    }
}
