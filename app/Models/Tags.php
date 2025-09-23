<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tags extends Model
{
    use HasFactory;

    // ✅ Explicit table name (if needed, but Laravel automatically uses 'tags')
    protected $table = 'tags';

    // ✅ Fields that can be mass-assigned
    protected $fillable = [
        'department_id',
        'description',
    ];

    /**
     * Relationship: A Tag belongs to a Department.
     */
    public function department()
    {
        return $this->belongsTo(Department::class);
    }
}
