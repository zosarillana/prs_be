<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Uom extends Model
{
    use HasFactory;

    protected $table = 'uom';

    protected $fillable = [
        'description',
    ];

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
}
