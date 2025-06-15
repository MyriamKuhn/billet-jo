<?php

namespace Database\Seeders;

use App\Models\Cart;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run(): void
    {
        // Create a admin user
        $admin = User::create([
            'email' => 'admin@yopmail.com',
            'email_verified_at' => now(),
            'password_hash' => Hash::make('Manuel10101982*'),
            'firstname' => 'Alice',
            'lastname' => 'Admin',
            'role' => 'admin',
            'twofa_secret' => null,
            'twofa_enabled' => false,
            'is_active' => true,
            'remember_token' => null,
        ]);
        // Create a cart for the admin user
        Cart::create([
            'user_id' => $admin->id,
        ]);

        // Create an employee user
        $employee = User::create([
            'email' => 'employee@yopmail.com',
            'email_verified_at' => now(),
            'password_hash' => Hash::make('Manuel10101982*'),
            'firstname' => 'Bob',
            'lastname' => 'Employee',
            'role' => 'employee',
            'twofa_secret' => null,
            'twofa_enabled' => false,
            'is_active' => true,
            'remember_token' => null,
        ]);
        // Create a cart for the employee user
        Cart::create([
            'user_id' => $employee->id,
        ]);

        // Create a user
        $user = User::create([
            'email' => 'user@yopmail.com',
            'email_verified_at' => now(),
            'password_hash' => Hash::make('Manuel10101982*'),
            'firstname' => 'Charlie',
            'lastname' => 'User',
            'role' => 'user',
            'twofa_secret' => null,
            'twofa_enabled' => false,
            'is_active' => true,
            'remember_token' => null,
        ]);
        // Create a cart for the user
        Cart::create([
            'user_id' => $user->id,
        ]);
    }
}
