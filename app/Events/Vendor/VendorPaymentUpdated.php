<?php

namespace App\Events\Vendor;

use App\Models\VendorPayment;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class VendorPaymentUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public VendorPayment $payment;
    public string $action;

    public function __construct(VendorPayment $payment, string $action)
    {
        $this->payment = $payment;
        $this->action = $action;
    }

    public function broadcastOn(): array
    {
        // This could be a private channel per role or a public one
        return [new PrivateChannel('vendor-payments')];
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->payment->id,
            'po_number' => $this->payment->po_number,
            'inv_number' => $this->payment->inv_number,
            'amount' => $this->payment->amount,
            'cheque_status' => $this->payment->cheque_status,
            'action' => $this->action,
        ];
    }
}