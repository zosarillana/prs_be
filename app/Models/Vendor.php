<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Vendor extends Model
{
    use HasFactory;

    protected $table = 'vendors';

    protected $fillable = [
        'vendor_name',
    ];

    public function itemPrices()
    {
        return $this->hasMany(ItemPrice::class);
    }

    public function items()
    {
        return $this->belongsToMany(Item::class, 'item_prices')
                    ->withPivot('unit_price')
                    ->withTimestamps();
    }
}
