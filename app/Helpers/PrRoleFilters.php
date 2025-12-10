<?php

namespace App\Helpers;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class PrRoleFilters
{
    public static function applyRoleFilters(Builder $query, User $user): Builder
    {
        $roles = collect($user->role ?? []);
        $departments = collect($user->department ?? [])->map(function ($dept) {
            return [
                'original' => $dept,
                'slug'     => preg_replace('/[^A-Za-z0-9_.-]/', '_', $dept),
            ];
        });

        // 🔹 Admin + purchasing always see everything (no filters applied)
        if ($roles->contains('admin') || $roles->contains('purchasing')) {
            return $query;
        }

        $isTR  = $roles->contains('technical_reviewer');
        $isHOD = $roles->contains('hod');
        $isUser = $roles->contains('user');

        // Build a flat department list
        $deptValues = $departments->flatMap(fn($d) => [$d['original'], $d['slug']])->all();

        /**
         * 🔥 DEPARTMENT-TAG MATCHER FOR TR
         */
        $applyDepartmentTagFilter = function (Builder $q) use ($deptValues) {
            $q->where(function ($deptQ) use ($deptValues) {
                foreach ($deptValues as $dept) {
                    $deptQ->orWhereRaw(
                        "JSON_SEARCH(JSON_EXTRACT(tag, '$[*].department'), 'one', ?) IS NOT NULL",
                        [$dept]
                    );
                }
            });
        };

        /**
         * ----------------------------------------
         * 1) Technical Reviewer ONLY
         * ----------------------------------------
         */
        if ($isTR && !$isHOD && !$isUser) {
            return $query->where(function ($q) use ($applyDepartmentTagFilter) {
                // A) Pending TR work
                $q->where(function ($sub) use ($applyDepartmentTagFilter) {
                    $sub->where('pr_status', 'on_hold_tr');
                    $applyDepartmentTagFilter($sub);
                });

                // B) Completed TR work
                $q->orWhere(function ($sub) use ($applyDepartmentTagFilter) {
                    $sub->whereNotNull('tr_user_id');
                    $applyDepartmentTagFilter($sub);
                });
            });
        }

        /**
         * ----------------------------------------
         * 2) HOD ONLY
         * ----------------------------------------
         * Simple: Just check department column (like old code)
         */
        if ($isHOD && !$isTR && !$isUser) {
            if (!empty($deptValues)) {
                return $query->whereIn('department', $deptValues);
            }
            return $query;
        }

        /**
         * ----------------------------------------
         * 3) USER ONLY
         * ----------------------------------------
         * Shows items where department column matches
         */
        if ($isUser && !$isHOD && !$isTR) {
            if (!empty($deptValues)) {
                return $query->whereIn('department', $deptValues);
            }
            return $query;
        }

        /**
         * ----------------------------------------
         * 4) HOD + USER COMBINATION
         * ----------------------------------------
         */
        if ($isHOD && $isUser && !$isTR) {
            if (!empty($deptValues)) {
                return $query->whereIn('department', $deptValues);
            }
            return $query;
        }

        /**
         * ----------------------------------------
         * 5) HOD + TR COMBINATION
         * ----------------------------------------
         */
        if ($isHOD && $isTR && !$isUser) {
            return $query->where(function ($outer) use ($deptValues, $applyDepartmentTagFilter) {
                // HOD part - just department column
                if (!empty($deptValues)) {
                    $outer->orWhereIn('department', $deptValues);
                }

                // TR part - tags with department match
                $outer->orWhere(function ($trQ) use ($applyDepartmentTagFilter) {
                    $trQ->where(function ($sub) use ($applyDepartmentTagFilter) {
                        $sub->where('pr_status', 'on_hold_tr');
                        $applyDepartmentTagFilter($sub);
                    });
                    $trQ->orWhere(function ($sub) use ($applyDepartmentTagFilter) {
                        $sub->whereNotNull('tr_user_id');
                        $applyDepartmentTagFilter($sub);
                    });
                });
            });
        }

        /**
         * ----------------------------------------
         * 6) ALL THREE ROLES COMBINATION
         * ----------------------------------------
         */
        if ($isHOD && $isTR && $isUser) {
            return $query->where(function ($outer) use ($deptValues, $applyDepartmentTagFilter) {
                // HOD/User part - just department column
                if (!empty($deptValues)) {
                    $outer->orWhereIn('department', $deptValues);
                }

                // TR part - tags with department match
                $outer->orWhere(function ($trQ) use ($applyDepartmentTagFilter) {
                    $trQ->where(function ($sub) use ($applyDepartmentTagFilter) {
                        $sub->where('pr_status', 'on_hold_tr');
                        $applyDepartmentTagFilter($sub);
                    });
                    $trQ->orWhere(function ($sub) use ($applyDepartmentTagFilter) {
                        $sub->whereNotNull('tr_user_id');
                        $applyDepartmentTagFilter($sub);
                    });
                });
            });
        }

        return $query;
    }

    /**
     * 🔹 SPECIAL METHOD FOR ADMIN DEPARTMENT FILTERING
     */
    public static function applyAdminDepartmentFilter(Builder $query, User $user): Builder
    {
        $departments = collect($user->department ?? [])->map(function ($dept) {
            return [
                'original' => $dept,
                'slug'     => preg_replace('/[^A-Za-z0-9_.-]/', '_', $dept),
            ];
        });

        $deptValues = $departments->flatMap(fn($d) => [$d['original'], $d['slug']])->all();

        if (empty($deptValues)) {
            return $query;
        }

        return $query->where(function ($q) use ($deptValues) {
            $q->whereIn('department', $deptValues);

            $q->orWhere(function ($deptQ) use ($deptValues) {
                foreach ($deptValues as $dept) {
                    $deptQ->orWhereRaw(
                        "JSON_SEARCH(JSON_EXTRACT(tag, '$[*].department'), 'one', ?) IS NOT NULL",
                        [$dept]
                    );
                }
            });
        });
    }
}
