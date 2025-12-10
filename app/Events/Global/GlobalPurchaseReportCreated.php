<?php

namespace App\Events\Global;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GlobalPurchaseReportCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $report;

    public function __construct($report)
    {
        $this->report = $report;
    }

    public function broadcastOn(): array
    {
        $channels = [];

        // Department isolated
        if ($this->report->department_slug) {
            $channels[] = new Channel('purchase-report-dept-' . $this->report->department_slug);
        }
        $channels[] = new Channel('purchase-report-user-' . $this->report->user_id);

        // Admin, Purchasing, HOD channels (if you want those roles to get all)
        $channels[] = new Channel('purchase-report-admin');
        $channels[] = new Channel('purchase-report-purchasing');
        $channels[] = new Channel('purchase-report-hod-' . $this->report->department_slug);

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'GlobalPurchaseReportCreated';
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
            'created_at' => $this->report->created_at->toISOString(),
            'type' => 'global_notification',
            'affects_all_users' => false,
            'affects_roles' => ['admin', 'hod', 'purchasing', 'technical_reviewer', 'user'],
            'affects_departments' => [$this->report->department],
        ];
    }

    public function broadcastConnection()
    {
        return 'reverb';
    }
}
