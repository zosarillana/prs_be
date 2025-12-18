<?php

namespace App\Service\ItemPrice;

use App\Contracts\TableQueryService;
use App\Models\ItemPrice;
use Illuminate\Database\Eloquent\Builder;

class ItemPriceService implements TableQueryService
{
    public function getQuery(array $params): Builder
    {
        $query = ItemPrice::query()
            ->with(['item', 'vendor']);

        // 🔍 Global search (item OR vendor)
        if (!empty($params['searchTerm'])) {
            $search = $params['searchTerm'];

            $query->where(function ($q) use ($search) {
                $q->whereHas('item', function ($i) use ($search) {
                    $i->where('item_name', 'like', "%{$search}%");
                })->orWhereHas('vendor', function ($v) use ($search) {
                    $v->where('vendor_name', 'like', "%{$search}%");
                });
            });
        }

        // 🔍 Item filter
        if (!empty($params['item'])) {
            $query->whereHas('item', function ($q) use ($params) {
                $q->where('item_name', 'like', '%' . $params['item'] . '%');
            });
        }

        // 🔍 Vendor filter
        if (!empty($params['vendor'])) {
            $query->whereHas('vendor', function ($q) use ($params) {
                $q->where('vendor_name', 'like', '%' . $params['vendor'] . '%');
            });
        }

        // 🔍 Price range
        if (!empty($params['minPrice'])) {
            $query->where('unit_price', '>=', $params['minPrice']);
        }

        if (!empty($params['maxPrice'])) {
            $query->where('unit_price', '<=', $params['maxPrice']);
        }

        return $query;
    }
}
