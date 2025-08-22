<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'quantity',
        'unit',
        'item_description',
        'tag',
        'remarks',
    ];

   protected $casts = [
        'user_id'          => 'integer', 
        'quantity'         => 'array',
        'unit'             => 'array',
        'item_description' => 'array',
        'tag'              => 'array',
        'remarks'          => 'array',
    ];
}
