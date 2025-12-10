<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseReportProgress extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_report_id',
        'start_date',
        'end_date',
        'title',
        'remarks',
        'created_by',
    ];

    protected $casts = [
        'start_date' => 'datetime:Y-m-d',
        'end_date' => 'datetime:Y-m-d',
    ];

    public function report()
    {
        return $this->belongsTo(PurchaseReport::class, 'purchase_report_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
