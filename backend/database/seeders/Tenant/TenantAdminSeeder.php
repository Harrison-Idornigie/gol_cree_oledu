<?php

namespace Database\Seeders\Tenant;

use App\Models\Tenants\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class TenantAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create tenant admin user
        User::create([
            'name' => 'Super Admin',
            'email' => 'test.superadmin@oledu.ca',
            'email_verified_at' => now(),
            'password' => Hash::make('password123'), // You should change this in production
            'remember_token' => Str::random(10),
            'role' => 'admin',
            'interface_language' => 'en',
            'is_active' => true,
        ]);

        $this->command->info('Super Admin user created successfully!');
    }
}