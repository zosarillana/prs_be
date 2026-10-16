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

                $itemName   = strtoupper(trim(preg_replace('/\s+/', ' ', $row['item_description'] ?? '')));
                $vendorName = strtoupper(trim(preg_replace('/\s+/', ' ', $row['suppliervendor'] ?? '')));
                $priceRaw   = trim($row['unit_price'] ?? '');

                if ($itemName === '' || $vendorName === '' || $priceRaw === '') {
                    continue;
                }

                $price = (float) str_replace(',', '', $priceRaw);

                $item = Item::firstOrCreate([
                    'item_name' => $itemName,
                ]);

                $vendor = Vendor::firstOrCreate([
                    'vendor_name' => $vendorName,
                ]);

                // ✅ skip ONLY exact duplicates
                $exists = ItemPrice::where([
                    'item_id'    => $item->id,
                    'vendor_id'  => $vendor->id,
                    'unit_price' => $price,
                ])->exists();

                if ($exists) {
                    continue;
                }

                ItemPrice::create([
                    'item_id'    => $item->id,
                    'vendor_id'  => $vendor->id,
                    'unit_price' => $price,
                ]);
            }
        });
    }
}
