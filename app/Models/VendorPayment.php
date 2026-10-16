<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VendorPayment extends Model
{
    use HasFactory;

    protected $table = 'vendor_payment';

    protected $fillable = [
        'po_number',
        'inv_number',
        'vendor_id',
        'prs_id',
        'amount',
        'cheque_status',
        'cheque_number',
        'date_served',
    ];

    protected $casts = [
        'date_served' => 'date',
    ];

    /**
     * Relationship: PaymentVendor belongs to a Vendor
     */
    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }

    public function purchaseReport()
    {
        return $this->belongsTo(PurchaseReport::class, 'prs_id');
    }
}
