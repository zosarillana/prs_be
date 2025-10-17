<?php

namespace App\Events\PurchaseReportEvents;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use App\Models\User;

class PurchaseReportCreated implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public $report;

    public function __construct($report)
    {
        $this->report = $report;
    }

    public function broadcastOn(): array
    {
        $channels = [];

        // 1. Always broadcast to the user who created the report
        $channels[] = new Channel('purchase-report-user-' . $this->report->user_id);

        // 2. Broadcast to all admins
        $channels[] = new Channel('purchase-report-admin');

        // 3. Broadcast to HODs in the same department (safe slug)
        if ($this->report->department) {
            $channels[] = new Channel('purchase-report-dept-' . $this->report->department_slug);
        }

        return $channels;
    }


    public function broadcastAs(): string
    {
        return 'PurchaseReportCreated';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->report->id,
            'series_no' => $this->report->series_no,
            'user_id' => $this->report->user_id,
            'department' => $this->report->department ?? null,
            'pr_status' => $this->report->pr_status,
            'po_status' => $this->report->po_status,
            'created_by' => $this->report->user->name ?? 'Unknown',
        ];
    }

    public function broadcastConnection()
    {
        return 'reverb';
    }
}
