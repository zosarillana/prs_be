<?php

namespace App\Events\PurchaseReportEvents;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PurchaseReportCreated implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public $report;

    public function __construct($report)
    {
        $this->report = $report;
    }

    public function broadcastOn(): Channel
    {
        return new Channel('purchase-report'); // public channel
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
        ];
    }

    // Add this method to explicitly use Reverb
    public function broadcastConnection()
    {
        return 'reverb';
    }
}