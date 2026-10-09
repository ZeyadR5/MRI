<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            TimeSlotSeeder::class,
        ]);

        // Default Super Admin
        User::firstOrCreate(
            ['email' => 'superadmin@mri.com'],
            [
                'first_name' => 'سوبر',
                'last_name' => 'أدمن',
                'name' => 'سوبر أدمن الرئيسي',
                'username' => 'superadmin',
                'password' => Hash::make('password123'),
                'role' => 'super_admin',
            ]
        );

        // Default Admin
        User::firstOrCreate(
            ['email' => 'admin@mri.com'],
            [
                'first_name' => 'مدير',
                'last_name' => 'النظام',
                'name' => 'أدمن المواعيد',
                'username' => 'admin',
                'password' => Hash::make('password123'),
                'role' => 'admin',
            ]
        );

        // Default User
        User::firstOrCreate(
            ['email' => 'user@mri.com'],
            [
                'first_name' => 'موظف',
                'last_name' => 'استقبال',
                'name' => 'موظف الاستقبال',
                'username' => 'user',
                'password' => Hash::make('password123'),
                'role' => 'user',
            ]
        );
    }
}
