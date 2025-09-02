<?php

namespace App\Helpers;

use App\Models\User;

class MapUsers
{
    public static function map(User $user): array
    {
        return [
            'id'         => $user->id,
            'name'       => $user->name,
            'email'      => $user->email,
            'department' => $user->department,
            'role'       => $user->role,
            'created_at' => $user->created_at ? $user->created_at->format('Y-m-d H:i:s') : null,
            'updated_at' => $user->updated_at ? $user->updated_at->format('Y-m-d H:i:s') : null,
        ];
    }

    public static function mapTable(User $user): array
    {
        return [
            'id'         => $user->id,
            'name'       => $user->name,
            'email'      => $user->email,
            'department' => $user->department,
            'role'       => $user->role,
            'created_at' => $user->created_at ? $user->created_at->format('Y-m-d') : null,
        ];
    }
}
