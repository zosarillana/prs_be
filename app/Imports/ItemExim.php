<?php

namespace App\Imports;

use App\Models\Item;
use App\Models\Vendor;
use App\Models\ItemPrice;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Illuminate\Support\Facades\Log;

class ItemExim implements ToCollection, WithHeadingRow
{
    public function collection(Collection $rows)
    {
        DB::transaction(function () use ($rows) {
            foreach ($rows as $row) {

                // DEBUG: log each row
                Log::info($row->toArray());

                // Skip incomplete rows
                if (empty($row['item_description']) || empty($row['suppliervendor']) || empty($row['unit_price'])) {
                    continue;
                }

                $item = Item::firstOrCreate([
                    'item_name' => trim($row['item_description']),
                ]);

                $vendor = Vendor::firstOrCreate([
                    'vendor_name' => trim($row['suppliervendor']),
                ]);

                ItemPrice::firstOrCreate(
                    [
                        'item_id' => $item->id,
                        'vendor_id' => $vendor->id,
                    ],
                    [
                        'unit_price' => (float) str_replace(',', '', $row['unit_price']),
                    ]
                );
            }
        });
    }
}
