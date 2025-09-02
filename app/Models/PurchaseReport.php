<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'series_no',
        'pr_purpose',
        'department',
        'date_submitted',
        'date_needed',
        'quantity',
        'unit',
        'item_description',
        'tag',
        'item_status',
        'pr_status',
        'remarks',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'quantity' => 'array',
        'unit' => 'array',
        'item_description' => 'array',
        'tag' => 'array',
        'item_status' => 'array',
        'remarks' => 'array',
        'date_submitted' => 'datetime:Y-m-d',
        'date_needed' => 'datetime:Y-m-d',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
