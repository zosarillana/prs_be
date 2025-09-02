<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PrSignature extends Model
{
    // Table name
    protected $table = 'pr_signatures';

    // If you’re using custom timestamp columns
    const CREATED_AT = 'date_created';
    const UPDATED_AT = 'date_updated';

    // If you want Laravel to manage timestamps
    public $timestamps = true;

    // Which fields are mass assignable
    protected $fillable = [
        'purchase_report_id',
        'user_id',
        'role',
        'signed_at',
        'is_approved',
    ];

    /**
     * A signature belongs to a purchase report.
     */
    public function purchaseReport()
    {
        return $this->belongsTo(PurchaseReport::class);
    }

    /**
     * A signature belongs to a user.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
