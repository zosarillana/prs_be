<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'series_no',
        'po_no',
        'po_status',
        'po_created_date',
        'po_approved_date',
        'purchaser_id',
        'pr_purpose',
        'department',
        'date_submitted',
        'date_needed',
        'quantity',
        'unit',
        'item_description',
        'tag',
        'item_status',
        'pr_status',
        'remarks',
        'tr_user_id',
        'hod_user_id',
        'tr_signed_at',
        'hod_signed_at',
        'delivery_status',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'tr_user_id' => 'integer',
        'hod_user_id' => 'integer',
        'quantity' => 'array',
        'unit' => 'array',
        'item_description' => 'array',
        'tag' => 'array',
        'item_status' => 'array',
        'remarks' => 'array',
        'date_submitted' => 'datetime:Y-m-d',
        'date_needed' => 'datetime:Y-m-d',
        'tr_signed_at' => 'datetime',
        'hod_signed_at' => 'datetime',
        'po_created_date' => 'datetime',
        'po_approved_date' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function trUser()
    {
        return $this->belongsTo(User::class, 'tr_user_id');
    }

    public function hodUser()
    {
        return $this->belongsTo(User::class, 'hod_user_id');
    }

    public function purchaserUser()
    {
        return $this->belongsTo(User::class, 'purchaser_id');
    }

    protected static function booted()
    {
        static::created(function ($model) {
            auditLog('created', $model, null, $model->getAttributes());
        });

        static::updated(function ($model) {
            $changes = $model->getChanges();
            if (!empty($changes)) {
                auditLog('updated', $model, $model->getOriginal(), $changes);
            }
        });

        static::deleted(function ($model) {
            auditLog('deleted', $model, $model->getOriginal(), null);
        });
    }

    /** ✅ Safe department slug accessor for broadcasting */
    public function getDepartmentSlugAttribute(): string
    {
        return preg_replace('/[^A-Za-z0-9_.-]/', '_', $this->department ?? '');
    }

    public function progresses()
    {
        return $this->hasMany(PurchaseReportProgress::class);
    }
}
