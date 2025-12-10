<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class NewMessageNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function via($notifiable)
    {
        return ['database', 'broadcast'];
    }

    /**
     * Store in database
     */
    public function toDatabase($notifiable)
    {
        return [
            'title'      => $this->data['title'] ?? null,
            'report_id'  => $this->data['report_id'] ?? null,
            'series_no'  => $this->data['series_no'] ?? null, // ✅ Add series_no
            'po_no'      => $this->data['po_no'] ?? null,     // ✅ Add po_no for consistency
            'created_by' => $this->data['created_by'] ?? null,
            'pr_status'  => $this->data['pr_status'] ?? null,
            'po_status'  => $this->data['po_status'] ?? null,
            'user_id'    => $this->data['user_id'] ?? null,
            'department' => $this->data['department'] ?? null,
            'role'       => $this->data['role'] ?? null,
        ];
    }

    public function toBroadcast($notifiable)
    {
        return new BroadcastMessage([
            'title'      => $this->data['title'] ?? null,
            'report_id'  => $this->data['report_id'] ?? null,
            'series_no'  => $this->data['series_no'] ?? null, // ✅ Add series_no
            'po_no'      => $this->data['po_no'] ?? null,     // ✅ Add po_no for consistency
            'created_by' => $this->data['created_by'] ?? null,
            'pr_status'  => $this->data['pr_status'] ?? null,
            'po_status'  => $this->data['po_status'] ?? null,
            'user_id'    => $this->data['user_id'] ?? null,
            'department' => $this->data['department'] ?? null,
            'role'       => $this->data['role'] ?? null,
        ]);
    }
}
