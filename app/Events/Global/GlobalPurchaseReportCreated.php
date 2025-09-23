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

    /**
     * Create a new event instance.
     */
    public function __construct($report)
    {
        $this->report = $report;
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): Channel
    {
        return new Channel('purchase-report-global'); // Public channel for all users
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'GlobalPurchaseReportCreated';
    }

    /**
     * Get the data to broadcast.
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
            'created_by' => $this->report->user->name ?? 'Unknown',
            'created_at' => $this->report->created_at->toISOString(),
            'type' => 'global_notification',
            'affects_all_users' => false, // Set to true for system-wide events
            'affects_roles' => ['admin', 'hod', 'purchasing', 'user'], // Roles that should see this
            'affects_departments' => [$this->report->department], // Departments affected
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