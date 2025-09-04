<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Service\User\UserService;
use App\Service\Paginator\PaginatorService;
use Illuminate\Validation\Rule;
use App\Helpers\MapUsers;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
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
            ->map(fn($user) => MapUsers::mapTable($user))
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

        return response()->json($user, 201);
    }

    public function show(string $id)
    {
        $user = $this->userService->getUserById($id);

        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        return response()->json($user, 200);
    }

    public function update(Request $request, string $id)
    {
        Log::info('Request headers', $request->headers->all());
        Log::info('Request all', $request->all());
        Log::info('Request files', $request->files->all());

        $user = $this->userService->getUserById($id);
        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        // Validate form fields (without file)
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => ['sometimes', 'email', Rule::unique('users')->ignore($user->id)],
            'password' => 'sometimes|string|min:6|confirmed',
            'department' => 'sometimes|array',
            'role' => 'sometimes|array',
        ]);

        // Get the file separately
        $file = $request->file('signature');

        // Merge file into data array if exists
        if ($file) {
            $validated['signature'] = $file;
        }

        $file = $request->file('signature');

        if ($file) {
            Log::info("Uploaded signature file info:", [
                'original_name' => $file->getClientOriginalName(),
                'size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
                'is_valid' => $file->isValid(),
            ]);
        } else {
            Log::info("No signature file uploaded");
        }

        $user = $this->userService->updateUser($user, $validated);

        return response()->json($user, 200);
    }

    public function updateSignature(Request $request, string $id)
    {
        $user = $this->userService->getUserById($id);
        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        $file = $request->file('signature');
        if ($file && $file->isValid()) {
            // Create a unique filename
            $filename = 'signature_' . $id . '_' . time() . '.png';

            // Resize the image to 577px width x 433px height
            $image = Image::read($file)
                ->resize(577, 433)
                ->toPng(false); // false = no interlacing

            // Store the resized image
            $path = 'signatures/' . $filename;
            Storage::disk('public')->put($path, $image);

            // Update user signature path
            $user->signature = Storage::url($path);
            $user->save();

            return response()->json($user, 200);
        } else {
            return response()->json(['message' => 'No signature file uploaded'], 400);
        }
    }

    public function destroy(string $id)
    {
        $user = $this->userService->getUserById($id);

        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        $this->userService->deleteUser($user);

        return response()->json(['message' => 'User deleted successfully'], 200);
    }
}
