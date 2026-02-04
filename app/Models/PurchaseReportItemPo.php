<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseReportItemPo extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_report_id',
        'item_index',
        'po_number',
        'purchaser_id',
        'status',
        'po_created_at',
        'po_approved_at',
    ];

    protected $casts = [
        'po_created_at' => 'datetime',
        'po_approved_at' => 'datetime',
    ];

    public function purchaseReport()
    {
        return $this->belongsTo(PurchaseReport::class);
    }

    public function purchaser()
    {
        return $this->belongsTo(User::class, 'purchaser_id');
    }
}
