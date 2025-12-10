<?php

namespace App\Helpers;

use App\Models\PurchaseReport;
use App\Models\User;

class MapPurchaseReport
{
    public static function map(PurchaseReport $report): array
    {
        return [
            'id' => $report->id,
            'series_no' => $report->series_no,
            'pr_purpose' => $report->pr_purpose,
            'department' => $report->department,
            'created_at' => $report->created_at,
            'date_submitted' => $report->date_submitted ? $report->date_submitted->format('Y-m-d') : null,
            'date_needed' => $report->date_needed ? $report->date_needed->format('Y-m-d') : null,
            'quantity' => $report->quantity,
            'unit' => $report->unit,
            'item_description' => $report->item_description,

            // ✅ Updated: normalize tags to ensure consistent array structure
            'tag' => self::mapTags($report->tag),

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
            'purchaser_id' => $report->purchaserUser ? self::mapUser($report->purchaserUser) : null,
            'department' => $report->department,

            // ✅ Updated: handle structured tag arrays
            'tag' => self::mapTags($report->tag),

            'pr_created' => $report->created_at ? $report->created_at->format('Y-m-d') : null,
            'pr_status' => $report->pr_status,
            'pr_purpose' => $report->pr_purpose,
            'series_no' => $report->series_no,
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

        // If already structured (id + description), return as-is
        if (is_array($tags) && isset($tags[0]['id'])) {
            return array_map(fn($tag) => [
                'id' => $tag['id'],
                'description' => $tag['description'] ?? null,
                'department' => $tag['department'] ?? null,
            ], $tags);
        }

        // Legacy fallback: convert ["Engineering_tr"] → [{"id"=>null,"description"=>"Engineering_tr"}]
        return array_map(fn($tag) => [
            'id' => null,
            'description' => is_string($tag) ? $tag : null,
            'department' => null,
        ], (array) $tags);
    }
}
