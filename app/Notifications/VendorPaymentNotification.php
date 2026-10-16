<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class VendorPaymentNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function toArray($notifiable)
    {
        return $this->data;
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
            'title' => $this->data['title'] ?? null,
            'payment_id' => $this->data['payment_id'] ?? null,
            'po_number' => $this->data['po_number'] ?? null,
            'inv_number' => $this->data['inv_number'] ?? null,
            'amount' => $this->data['amount'] ?? null,
            'cheque_status' => $this->data['cheque_status'] ?? null,
            'created_by' => $this->data['created_by'] ?? null,
            'department' => $this->data['department'] ?? null,
            'role' => $this->data['role'] ?? null,
        ];
    }

    /**
     * Broadcast in real-time
     */
    public function toBroadcast($notifiable)
    {
        return new BroadcastMessage([
            'title' => $this->data['title'] ?? null,
            'payment_id' => $this->data['payment_id'] ?? null,
            'po_number' => $this->data['po_number'] ?? null,
            'inv_number' => $this->data['inv_number'] ?? null,
            'amount' => $this->data['amount'] ?? null,
            'cheque_status' => $this->data['cheque_status'] ?? null,
            'created_by' => $this->data['created_by'] ?? null,
            'department' => $this->data['department'] ?? null,
            'role' => $this->data['role'] ?? null,
        ]);
    }
}
