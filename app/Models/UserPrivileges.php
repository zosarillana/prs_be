<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserPrivileges extends Model
{
    use HasFactory;

    protected $table = 'user_priviliges'; // matches your migration table

    protected $fillable = [
        'user_id',
        'tag_ids',
        'module_ids',
    ];

    // Cast JSON columns to array for easy access
    protected $casts = [
        'tag_ids' => 'array',
        'module_ids' => 'array',
    ];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
