<?php

namespace App\Events\Global;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GlobalDepartmentCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $department;

    public function __construct($department)
    {
        $this->department = $department;
    }

    public function broadcastOn(): Channel
    {
        // ✅ SAME CHANNEL so the frontend listener automatically receives it
        return new Channel('purchase-report-global');
    }

    public function broadcastAs(): string
    {
        return 'GlobalDepartmentCreated';
    }

    public function broadcastWith(): array
    {
        return [
            'id'   => $this->department->id,
            'name' => $this->department->name,
            'description' => $this->department->description,
            'created_at'  => $this->department->created_at->toISOString(),

            // 🔑 IMPORTANT: This lets your frontend know this is a **global** event
            'type' => 'global_notification',
            'affects_all_users' => true,
            'affects_roles' => ['admin', 'hod', 'purchasing', 'user'],
            'affects_departments' => [], // empty because it's for everyone
        ];
    }

    public function broadcastConnection()
    {
        return 'reverb';
    }
}
