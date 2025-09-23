<?php

namespace App\Service\UserPriviliges;

use App\Models\UserPrivileges;
use DB;

class UserPriviligesService
{
  public function getAll()
    {
        return UserPrivileges::with('user')->get();
    }

    public function getById($id)
    {
        return UserPrivileges::with('user')->findOrFail($id);
    }

    public function create(array $data): UserPrivileges
    {
        return DB::transaction(function () use ($data) {
            return UserPrivileges::create([
                'user_id'    => $data['user_id'],
                'tag_ids'    => $data['tag_ids'] ?? [],
                'module_ids' => $data['module_ids'] ?? [],
            ]);
        });
    }

    public function update(UserPrivileges $privilege, array $data): UserPrivileges
    {
        return DB::transaction(function () use ($privilege, $data) {
            $privilege->update([
                'tag_ids'    => $data['tag_ids'] ?? $privilege->tag_ids,
                'module_ids' => $data['module_ids'] ?? $privilege->module_ids,
            ]);
            return $privilege;
        });
    }

    public function delete(UserPrivileges $privilege): bool
    {
        return $privilege->delete();
    }
}