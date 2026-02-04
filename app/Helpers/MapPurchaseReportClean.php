<?php

namespace App\Helpers;

use App\Models\PurchaseReport;
use App\Models\User;
use App\Models\UserPrivileges;

class MapPurchaseReportClean
{
    /**
     * Clean API mapper
     */
    public static function map(PurchaseReport $report): array
    {
        return [
            'id' => $report->id,
            'series_no' => $report->series_no,
            'pr_purpose' => $report->pr_purpose,
            'department' => $report->department,
            'pr_status' => $report->pr_status,
            'delivery_status' => $report->delivery_status,

            'dates' => [
                'created_at' => optional($report->created_at)->toISOString(),
                'submitted' => optional($report->date_submitted)->format('Y-m-d'),
                'needed' => optional($report->date_needed)->format('Y-m-d'),
            ],

            'user' => $report->user ? self::mapUser($report->user) : null,
            'purchaser' => self::mapPurchaser($report),

            'approvals' => [
                'tr_user' => $report->trUser ? self::mapUser($report->trUser) : null,
                'hod_user' => $report->hodUser ? self::mapUser($report->hodUser) : null,
                'tr_signed_at' => optional($report->tr_signed_at)->format('Y-m-d'),
                'hod_signed_at' => optional($report->hod_signed_at)->format('Y-m-d'),
            ],

            // ⭐ Clean items output
            'items' => self::mapItems($report),
        ];
    }

    /**
     * Convert parallel arrays into item objects
     */
    protected static function mapItems(PurchaseReport $report): array
    {
        $quantities   = (array) $report->quantity;
        $units        = (array) $report->unit;
        $descriptions = (array) $report->item_description;
        $statuses     = (array) $report->item_status;
        $remarks      = (array) $report->remarks;
        $tags         = self::mapTags($report->tag);

        $count = max(
            count($quantities),
            count($units),
            count($descriptions),
            count($statuses),
            count($remarks),
            count($tags)
        );

        $items = [];

        for ($i = 0; $i < $count; $i++) {
            $items[] = [
                'quantity' => $quantities[$i] ?? null,
                'unit' => $units[$i] ?? null,
                'description' => $descriptions[$i] ?? null,
                'status' => $statuses[$i] ?? null,
                'remarks' => $remarks[$i] ?? null,
                'tag' => $tags[$i] ?? null,
            ];
        }

        return $items;
    }

    /**
     * Normalize tags
     */
    protected static function mapTags($tags): array
    {
        if (empty($tags)) {
            return [];
        }

        return array_map(function ($tag) {
            if (empty($tag)) {
                return null;
            }

            if (is_array($tag) && isset($tag['id'])) {
                return [
                    'id' => $tag['id'],
                    'description' => $tag['description'] ?? '',
                    'department' => $tag['department'] ?? null,
                ];
            }

            return [
                'id' => null,
                'description' => (string) $tag,
                'department' => null,
            ];
        }, (array) $tags);
    }

    /**
     * Map user safely
     */
    protected static function mapUser(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'department' => $user->department,
            'role' => $user->role,
            'signature' => $user->signature,
        ];
    }

    /**
     * Resolve purchaser
     */
    protected static function mapPurchaser(PurchaseReport $report): ?array
    {
        if ($report->purchaserUser) {
            $roles = (array) $report->purchaserUser->role;

            if (count($roles) === 1 && in_array('purchasing', $roles, true)) {
                return self::mapUser($report->purchaserUser);
            }
        }

        return self::resolvePurchaserByTags($report);
    }

    /**
     * Fallback purchaser resolver via tags
     */
    protected static function resolvePurchaserByTags(PurchaseReport $report): ?array
    {
        $tagIds = collect(self::mapTags($report->tag))
            ->pluck('id')
            ->filter()
            ->values()
            ->toArray();

        if (empty($tagIds)) {
            return null;
        }

        $privilege = UserPrivileges::with('user')
            ->where(function ($query) use ($tagIds) {
                foreach ($tagIds as $tagId) {
                    $query->orWhereJsonContains('tag_ids', $tagId);
                }
            })
            ->whereHas('user', function ($query) {
                $query->whereJsonLength('role', 1)
                      ->whereJsonContains('role', 'purchasing');
            })
            ->first();

        return $privilege && $privilege->user
            ? self::mapUser($privilege->user)
            : null;
    }
}
