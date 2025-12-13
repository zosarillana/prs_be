<?php

namespace App\Events\Global;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use App\Models\PurchaseReport;

class GlobalPurchaseReportApprovalUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $report;
    public $action;
    public $oldStatus;

    public function __construct(PurchaseReport $report, string $action, ?string $oldStatus = null)
    {
        $this->report = $report;
        $this->action = $action;
        $this->oldStatus = $oldStatus;
    }

    public function broadcastOn(): array
    {
        $channels = [];
        if ($this->report->department_slug) {
            $channels[] = new Channel('purchase-report-dept-' . $this->report->department_slug);
        }
        $channels[] = new Channel('purchase-report-user-' . $this->report->user_id);
        $channels[] = new Channel('purchase-report-admin');
        $channels[] = new Channel('purchase-report-purchasing');
        $channels[] = new Channel('purchase-report-hod-' . $this->report->department_slug);

        // Optional: if you do want a global approval channel for admin only
        // $channels[] = new Channel('purchase-report-approval-global');

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'GlobalPurchaseReportApprovalUpdated';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->report->id,
            'series_no' => $this->report->series_no,
            'user_id' => $this->report->user_id,
            'department' => $this->report->department ?? null,
            'pr_status' => $this->report->pr_status,
            'old_pr_status' => $this->oldStatus,
            'po_status' => $this->report->po_status,
            'po_no' => $this->report->po_no,
            'created_by' => $this->report->user->name ?? 'Unknown',
            'created_at' => $this->report->created_at->toISOString(),
            'action' => $this->action,
            'type' => 'global_approval_notification',
            'affects_all_users' => false,
            'affects_departments' => [$this->report->department],
            'affects_roles' => ['admin', 'purchasing', 'hod', 'technical_reviewer', 'user'],
        ];
    }

    public function broadcastConnection()
    {
        return 'reverb';
    }
}
