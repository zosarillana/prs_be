<?php

namespace App\Service\VendorPayment;

use App\Models\VendorPayment;
use App\Service\Paginator\PaginatorService;

class VendorPaymentService
{
    public function __construct(private PaginatorService $paginator) {}

    /**
     * Get all payment vendors
     */
    public function all()
    {
        return VendorPayment::query()->get();
    }

    /**
     * Paginate payment vendors using custom PaginatorService
     */
    public function paginate(int $pageNumber = 1, int $pageSize = 15, ?string $search = null): array
    {
        $query = VendorPayment::query()
            ->with('vendor')
            ->with('purchaseReport');

        if ($search) {
            $query->where(function ($q) use ($search) {
                // Search root VendorPayment columns
                $columns = (new VendorPayment)->getFillable(); // e.g., po_number, vendor_id, etc.
                foreach ($columns as $column) {
                    $q->orWhere($column, 'like', "%{$search}%");
                }

                // Search related vendor
                $q->orWhereHas('vendor', function ($q2) use ($search) {
                    $q2->where('vendor_name', 'like', "%{$search}%");
                });

                // Search related purchase report
                $q->orWhereHas('purchaseReport', function ($q3) use ($search) {
                    $q3->where('series_no', 'like', "%{$search}%")
                        ->orWhere('po_no', 'like', "%{$search}%")
                        ->orWhere('pr_status', 'like', "%{$search}%")
                        ->orWhere('department', 'like', "%{$search}%");
                    // You can add more fields as needed
                });
            });
        }

        return $this->paginator->paginate($query, $pageNumber, $pageSize);
    }

    /**
     * Find a payment vendor by ID
     */
    public function find(int $id)
    {
        return VendorPayment::query()->find($id);
    }

    /**
     * Create a new payment vendor
     */
    public function create(array $data)
    {
        return VendorPayment::query()->create($data);
    }

    /**
     * Update a payment vendor
     */
    public function update(int $id, array $data)
    {
        $vendor = VendorPayment::query()->findOrFail($id);
        $vendor->update($data);

        return $vendor;
    }

    /**
     * Delete a payment vendor
     */
    public function delete(int $id): bool
    {
        $vendor = VendorPayment::query()->findOrFail($id);

        return $vendor->delete();
    }

    /**
     * Find by arbitrary column (useful helper)
     */
    public function findBy(string $column, mixed $value)
    {
        return VendorPayment::query()
            ->where($column, $value)
            ->first();
    }

    /**
     * Set the date_served and mark the cheque as closed
     */
    public function serveDate(int $id, ?string $dateServed = null)
    {
        $vendorPayment = VendorPayment::query()->findOrFail($id);

        // Use current date if none provided
        $vendorPayment->date_served = $dateServed ?? now();
        $vendorPayment->cheque_status = 'closed';

        $vendorPayment->save();

        return $vendorPayment;
    }
}
