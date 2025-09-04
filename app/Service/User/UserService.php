<?php

namespace App\Service\User;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class UserService
{
    public function getQuery(array $filters = [])
    {
        $query = User::query();

        // Search
        if (!empty($filters['searchTerm'])) {
            $term = $filters['searchTerm'];
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                    ->orWhere('email', 'like', "%{$term}%");
            });
        }

        // Sorting
        if (!empty($filters['sortBy'])) {
            $query->orderBy(
                $filters['sortBy'],
                $filters['sortOrder'] ?? 'asc'
            );
        }

        return $query;
    }
    public function getAllUsers()
    {
        return User::all();
    }

    public function createUser(array $data)
    {
        return User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);
    }

    public function getUserById(string $id)
    {
        return User::find($id);
    }


    public function updateUser(User $user, array $data)
    {
        if (isset($data['name'])) {
            $user->name = $data['name'];
        }

        if (isset($data['email'])) {
            $user->email = $data['email'];
        }

        if (isset($data['password'])) {
            $user->password = Hash::make($data['password']);
        }

        if (isset($data['department'])) {
            $user->department = $data['department'];
        }

        if (isset($data['role'])) {
            $user->role = $data['role'];
        }

        // ✅ Handle signature image upload
        if (isset($data['signature']) && $data['signature']->isValid()) {
            // Store in /storage/app/public/signatures
            $path = $data['signature']->store('signatures', 'public');

            // Save only the URL (or path) in DB
            $user->signature = Storage::url($path);
        }

        $user->save();

        return $user;
    }


    public function deleteUser(User $user)
    {
        return $user->delete();
    }
}
