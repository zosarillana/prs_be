<?php

namespace App\Helpers;

use App\Models\PurchaseReport;
use App\Models\User;
use App\Models\UserPrivileges;

class MapPurchaseReport
{
    public static function map(PurchaseReport $report): array
    {
        return [
            'id' => $report->id,
            'series_no' => $report->series_no,
            'sap_id' => $report->sap_id,
            'pr_purpose' => $report->pr_purpose,
            'purchaser_id' => self::mapPurchaser($report),
            'department' => $report->department,
            'created_at' => $report->created_at,
            'date_submitted' => $report->date_submitted ? $report->date_submitted->format('Y-m-d') : null,
            'date_needed' => $report->date_needed ? $report->date_needed->format('Y-m-d') : null,
            'quantity' => $report->quantity,
            'unit' => $report->unit,
            'item_description' => $report->item_description,

            // ✅ Updated: normalize tags to ensure consistent array structure
            'tag' => self::mapTags($report->tag),

            'item_pos' => self::mapItemPos($report->itemPos),
            
            'item_status' => $report->item_status,
            'remarks' => $report->remarks,
            'user' => $report->user ? self::mapUser($report->user) : null,
            'tr_user_id' => $report->trUser ? self::mapUser($report->trUser) : null,
            'hod_user_id' => $report->hodUser ? self::mapUser($report->hodUser) : null,
            'tr_signed_at' => $report->tr_signed_at ? $report->tr_signed_at->format('Y-m-d') : null,
            'hod_signed_at' => $report->hod_signed_at ? $report->hod_signed_at->format('Y-m-d') : null,
            'po_status' => $report->po_status,
            'delivery_status' => $report->delivery_status,
        ];
    }

    public static function mapTable(PurchaseReport $report): array
    {
        return [
            'user' => $report->user ? [
                'id' => $report->user->id,
                'name' => $report->user->name,
                'email' => $report->user->email,
                'department' => $report->user->department,
                'role' => $report->user->role,
            ] : null,
            'tr_user_id' => $report->trUser ? self::mapUser($report->trUser) : null,
            'hod_user_id' => $report->hodUser ? self::mapUser($report->hodUser) : null,
            'id' => $report->id,
            'po_no' => $report->po_no,
            'po_status' => $report->po_status,
            'po_created_date' => $report->po_created_date ? $report->po_created_date->format('Y-m-d') : null,
            'po_approved_date' => $report->po_approved_date ? $report->po_approved_date->format('Y-m-d') : null,
            // 'purchaser_id' => $report->purchaserUser ? self::mapUser($report->purchaserUser) : null,
            'purchaser_id' => self::mapPurchaser($report),
            'department' => $report->department,

            // ✅ Updated: handle structured tag arrays
            'tag' => self::mapTags($report->tag),

            'item_pos' => self::mapItemPos($report->itemPos),

            'pr_created' => $report->created_at ? $report->created_at->format('Y-m-d') : null,
            'pr_status' => $report->pr_status,
            'pr_purpose' => $report->pr_purpose,
            'series_no' => $report->series_no,
            'sap_id' => $report->sap_id,
            'hod_signed_at' => $report->hod_signed_at ? $report->hod_signed_at->format('Y-m-d') : null,
            'tr_signed_at' => $report->tr_signed_at ? $report->tr_signed_at->format('Y-m-d') : null,
            'date_created' => $report->created_at ? $report->created_at->format('Y-m-d') : null,
            'date_submitted' => $report->date_submitted ? $report->date_submitted->format('Y-m-d') : null,
            'date_needed' => $report->date_needed ? $report->date_needed->format('Y-m-d') : null,
            'delivery_status' => $report->delivery_status,
        ];
    }

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
     * ✅ Normalize the tag data
     * Handles both legacy (array of strings) and new structured format
     */
    protected static function mapTags($tags): array
    {
        if (empty($tags)) {
            return [];
        }

        return array_map(function ($tag) {
            // ✅ Handle empty string case
            if (empty($tag) || $tag === '') {
                return [
                    'id' => null,
                    'description' => '',
                    'department' => null,
                ];
            }

            // If already structured (id + description), return as-is
            if (is_array($tag) && isset($tag['id'])) {
                return [
                    'id' => $tag['id'],
                    'description' => $tag['description'] ?? '',
                    'department' => $tag['department'] ?? null,
                ];
            }

            // Legacy fallback: convert string to structure
            return [
                'id' => null,
                'description' => is_string($tag) ? $tag : '',
                'department' => null,
            ];
        }, (array) $tags);
    }

    protected static function resolvePurchaser(PurchaseReport $report): ?array
    {
        if (empty($report->tag)) {
            return null;
        }

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
                // 🔒 STRICT: role must be EXACTLY ["purchasing"]
                $query->whereJsonLength('role', 1)
                    ->whereJsonContains('role', 'purchasing');
            })
            ->first();

        if (! $privilege || ! $privilege->user) {
            return null;
        }

        return self::mapUser($privilege->user);
    }

    protected static function mapPurchaser(PurchaseReport $report): ?array
    {
        if ($report->purchaserUser) {
            $roles = (array) $report->purchaserUser->role;

            // ✅ Prioritize ONLY if role is strictly ["purchasing"]
            if (count($roles) === 1 && in_array('purchasing', $roles, true)) {
                return self::mapUser($report->purchaserUser);
            }
        }

        // ❌ Otherwise (multiple roles OR no purchaser) → resolve dynamically
        return self::resolvePurchaser($report);
    }

    /**
     * ✅ Normalize per-item PO data
     * Handles empty, unloaded, or partial relations safely
     */
    protected static function mapItemPos($itemPos): array
    {
        if (empty($itemPos)) {
            return [];
        }

        return collect($itemPos)->map(function ($itemPo) {
            return [
                'id' => $itemPo->id ?? null,
                'item_index' => $itemPo->item_index ?? null,
                'po_number' => $itemPo->po_number ?? null,
                'status' => $itemPo->status ?? null,
                'po_created_at' => $itemPo->po_created_at
                    ? $itemPo->po_created_at->format('Y-m-d')
                    : null,
                'po_approved_at' => $itemPo->po_approved_at
                    ? $itemPo->po_approved_at->format('Y-m-d')
                    : null,

                'purchaser' => $itemPo->purchaser
                    ? self::mapUser($itemPo->purchaser)
                    : null,
            ];
        })->values()->toArray();
    }
}
