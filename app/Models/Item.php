<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Item extends Model
{
    use HasFactory;

    protected $table = 'items';

    protected $fillable = [
        'item_name',
    ];

    public function itemPrices()
    {
        return $this->hasMany(ItemPrice::class);
    }

    public function vendors()
    {
        return $this->belongsToMany(Vendor::class, 'item_prices')
                    ->withPivot('unit_price')
                    ->withTimestamps();
    }
}
