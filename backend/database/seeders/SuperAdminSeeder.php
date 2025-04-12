<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class SuperAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create super admin user
        User::create([
            'name' => 'Super Admin',
            'email' => 'test.superadmin@oledu.ca',
            'email_verified_at' => now(),
            'password' => Hash::make('password123'), // You should change this in production
            'remember_token' => Str::random(10),
            'role' => 'admin',
            'points' => 0,
        ]);

        $this->command->info('Super Admin user created successfully!');
    }
}