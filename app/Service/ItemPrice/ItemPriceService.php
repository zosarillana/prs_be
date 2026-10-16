<?php

namespace App\Service\ItemPrice;

use App\Contracts\TableQueryService;
use App\Models\ItemPrice;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ItemPriceService implements TableQueryService
{
    /**
     * TABLE QUERY (list / search)
     */
    public function getQuery(array $params): Builder
    {
        $query = ItemPrice::query()
            ->with(['item', 'vendor']);

        // 🔍 Free text search
        if (!empty($params['searchTerm'])) {
            $search = $params['searchTerm'];

            $query->where(function ($q) use ($search) {
                $q->whereHas('item', fn ($i) =>
                    $i->where('item_name', 'like', "%{$search}%")
                )
                ->orWhereHas('vendor', fn ($v) =>
                    $v->where('vendor_name', 'like', "%{$search}%")
                )
                ->orWhere(
                    DB::raw('CAST(unit_price AS CHAR)'),
                    'like',
                    "%{$search}%"
                );
            });
        }

        if (!empty($params['item'])) {
            $query->whereHas('item', fn ($q) =>
                $q->where('item_name', 'like', '%' . $params['item'] . '%')
            );
        }

        if (!empty($params['vendor'])) {
            $query->whereHas('vendor', fn ($q) =>
                $q->where('vendor_name', 'like', '%' . $params['vendor'] . '%')
            );
        }

        return $query;
    }

    /**
     * CREATE
     */
    public function create(array $data): ItemPrice
    {
        return ItemPrice::create($data);
    }

    /**
     * READ (single)
     */
    public function findOrFail(int $id): ItemPrice
    {
        return ItemPrice::with(['item', 'vendor'])->findOrFail($id);
    }

    /**
     * UPDATE
     */
    public function update(int $id, array $data): ItemPrice
    {
        $itemPrice = $this->findOrFail($id);
        $itemPrice->update($data);

        return $itemPrice->refresh();
    }

    /**
     * DELETE
     */
    public function delete(int $id): void
    {
        $this->findOrFail($id)->delete();
    }
}
