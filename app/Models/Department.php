<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    use HasFactory;

    // ✅ Explicit table name (because migration uses "department" not "departments")
    protected $table = 'department';

    // ✅ Fields that can be mass-assigned
    protected $fillable = [
        'description',
        'name',
    ];
}
