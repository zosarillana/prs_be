<?php

namespace App\Helpers;

use App\Models\ItemPrice;

class MapItemPrice
{
    public static function mapTable(ItemPrice $price): array
    {
        return [
            'id' => $price->id,

            'item' => $price->item ? [
                'id' => $price->item->id,
                'name' => $price->item->item_name,
            ] : null,

            'vendor' => $price->vendor ? [
                'id' => $price->vendor->id,
                'name' => $price->vendor->vendor_name,
            ] : null,

            'unit_price' => number_format($price->unit_price, 2),

            'created_at' => $price->created_at
                ? $price->created_at->format('Y-m-d')
                : null,
        ];
    }
}
