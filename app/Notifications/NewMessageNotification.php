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
            'title'      => $this->data['title'],
            'report_id'  => $this->data['report_id'],
            'created_by' => $this->data['created_by'],
            'pr_status'  => $this->data['pr_status'],
            'po_status'  => $this->data['po_status'],
            'user_id'    => $this->data['user_id'],
            'department' => $this->data['department'] ?? null, // 🔹 add department
            'role'       => $this->data['role'] ?? null,       // optional
        ];
    }

    public function toBroadcast($notifiable)
    {
        return new BroadcastMessage([
            'title'      => $this->data['title'],
            'report_id'  => $this->data['report_id'],
            'created_by' => $this->data['created_by'],
            'pr_status'  => $this->data['pr_status'],
            'po_status'  => $this->data['po_status'],
            'user_id'    => $this->data['user_id'],
            'department' => $this->data['department'] ?? null, // 🔹 add department
            'role'       => $this->data['role'] ?? null,       // optional
        ]);
    }
}
