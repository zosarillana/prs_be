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
            'id' => $report->id,
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
}
