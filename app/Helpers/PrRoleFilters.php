<?php

namespace App\Helpers;

use Illuminate\Database\Eloquent\Builder;
use App\Models\User;

class PrRoleFilters
{
    public static function applyRoleFilters(Builder $query, User $user): Builder
    {
        $departments = $user->department ?? [];
        $roles       = $user->role ?? [];

        in_array('purchasing', $roles)
            ? $query->whereIn('pr_status', ['for_approval', 'closed'])
            : $query->whereIn('department', $departments);

        if (in_array('technical_reviewer', $roles)) {
            $query->where(fn($q) =>
                $q->where(fn($sub) =>
                    $sub->where('pr_status', 'for_approval')
                        ->whereRaw("JSON_SEARCH(JSON_EXTRACT(tag, '$'), 'one', '%_tr') IS NOT NULL")
                )->orWhere('pr_status', 'on_hold_tr')
            );
        }

        return $query;
    }
}
