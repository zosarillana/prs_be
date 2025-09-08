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
            'date_submitted' => $report->date_submitted->format('Y-m-d'),
            'date_needed' => $report->date_needed->format('Y-m-d'),
            'quantity' => $report->quantity,
            'unit' => $report->unit,
            'item_description' => $report->item_description,
            'tag' => $report->tag,
            'item_status' => $report->item_status,

            'remarks' => $report->remarks,
            'user' => $report->user ? self::mapUser($report->user) : null,
            'tr_user_id' => $report->trUser ? self::mapUser($report->trUser) : null,
            'hod_user_id' => $report->hodUser ? self::mapUser($report->hodUser) : null,
            'tr_signed_at' => $report->tr_signed_at ? $report->tr_signed_at->format('Y-m-d') : null,
            'hod_signed_at' => $report->hod_signed_at ? $report->hod_signed_at->format('Y-m-d') : null,
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
            'po_created_date' => $report->po_created_date ? $report->po_created_date->format('Y-m-d H:i:s') : null,
            'po_approved_date' => $report->po_approved_date ? $report->po_approved_date->format('Y-m-d H:i:s') : null,
            'department' => $report->department,
            'tag' => $report->tag,
            'pr_status' => $report->pr_status,
            'pr_purpose' => $report->pr_purpose,
            'series_no' => $report->series_no,
            'date_created' => $report->created_at ? $report->created_at->format('Y-m-d') : null,
            'date_submitted' => $report->date_submitted ? $report->date_submitted->format('Y-m-d') : null,
            'date_needed' => $report->date_needed ? $report->date_needed->format('Y-m-d') : null,
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

}
