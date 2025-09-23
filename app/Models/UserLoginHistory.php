<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserLoginHistory extends Model
{
    use HasFactory;

    // 🔑 Add these fields so mass-assignment works
    protected $fillable = [
        'user_id',
        'ip_address',
        'user_agent',
        'logged_in_at',
        'logged_out_at',
    ];

    // (optional) If you don't want timestamps (created_at/updated_at)
    // public $timestamps = false;

    // Relationships (optional)
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}