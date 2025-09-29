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
    public $action; // e.g. item_approved, po_created, po_cancelled, po_approved

    /**
     * Create a new event instance.
     */
    public function __construct(PurchaseReport $report, string $action = 'approval_updated')
    {
        $this->report = $report;
        $this->action = $action;
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): Channel
    {
        return new Channel('purchase-report-approval-global');
    }

    /**
     * Broadcast event name
     */
    public function broadcastAs(): string
    {
        return 'GlobalPurchaseReportApprovalUpdated';
    }

    /**
     * Data to broadcast
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->report->id,
            'series_no' => $this->report->series_no,
            'user_id' => $this->report->user_id,
            'department' => $this->report->department ?? null,
            'pr_status' => $this->report->pr_status,
            'po_status' => $this->report->po_status,
            'po_no' => $this->report->po_no,
            'created_by' => $this->report->user->name ?? 'Unknown',
            'created_at' => $this->report->created_at->toISOString(),
            'action' => $this->action, // <-- tells frontend what changed
            'type' => 'global_approval_notification',
            'affects_all_users' => false,
            'affects_roles' => ['admin','hod','purchasing','technical_reviewer','user'],
            'affects_departments' => [$this->report->department],
        ];
    }

    /**
     * Use Reverb connection
     */
    public function broadcastConnection()
    {
        return 'reverb';
    }
}
