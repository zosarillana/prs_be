<?php

namespace App\Helpers;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class PrRoleFilters
{
    public static function applyRoleFilters(Builder $query, User $user): Builder
    {
        $departments = $user->department ?? [];
        $roles = $user->role ?? [];

        // ✅ Start a wrapper so we can OR in "cancelled" globally
        $query->where(function ($outer) use ($roles, $departments) {
            $outer->where('pr_status', 'cancelled') // <-- always allow cancelled
                ->orWhere(function ($inner) use ($roles, $departments) {

                    if (in_array('purchasing', $roles)) {
                        // purchasing: keep old status limits
                        $inner->whereIn('pr_status', ['for_approval', 'closed']);
                    } else {
                        // others: still limit by department
                        $inner->whereIn('department', $departments);
                    }
                });
        });

        // ✅ Technical reviewer extra logic
        if (in_array('technical_reviewer', $roles)) {
            $query->where(function ($q) {
                $q->where(function ($sub) {
                    $sub->where('pr_status', 'for_approval')
                        ->whereRaw("JSON_SEARCH(JSON_EXTRACT(tag, '$'), 'one', '%_tr') IS NOT NULL");
                })->orWhere('pr_status', 'on_hold_tr');
            });
        }

        return $query;
    }
}
