<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::create([
            'name'       => 'Zoren Sarillana',
            'email'      => 'zoren.sarillana@agrieximfze.com',
            'password'   => Hash::make('@Temp1234!'),
            'role'       => ['admin'],          // Stored as array
            'department' => ['ict_department'], // Stored as array
            'signature'  => null,
        ]);

        User::create([
            'name'       => 'Ely Rebusa',
            'email'      => 'ely.rebusa@agrieximfze.com',
            'password'   => Hash::make('@Temp1234!'),
            'role'       => ['admin'],
            'department' => ['ict_department'],
            'signature'  => null,
        ]);
    }
}
