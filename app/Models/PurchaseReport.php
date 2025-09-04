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
        'tr_user_id',
        'hod_user_id',
        'tr_signed_at',
        'hod_signed_at',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'tr_user_id' => 'integer',     // Add this cast
        'hod_user_id' => 'integer',    // Add this cast
        'quantity' => 'array',
        'unit' => 'array',
        'item_description' => 'array',
        'tag' => 'array',
        'item_status' => 'array',
        'remarks' => 'array',
        'date_submitted' => 'datetime:Y-m-d',
        'date_needed' => 'datetime:Y-m-d',
        'tr_signed_at' => 'datetime',   // Add this cast
        'hod_signed_at' => 'datetime',  // Add this cast
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function trUser()
    {
        return $this->belongsTo(User::class, 'tr_user_id');
    }

    public function hodUser()
    {
        return $this->belongsTo(User::class, 'hod_user_id');
    }
}