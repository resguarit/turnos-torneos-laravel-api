<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::factory()->create([
            'name' => 'admin',
            'email' => 'admin@gmail.com',
            'password' => bcrypt('password'),
            'telefono' => '1234567890',
            'dni' => '123',
            'rol' => 'admin',
        ]);

        User::factory()->create([
            'name' => 'moderador',
            'email' => 'moderador@gmail.com',
            'password' => bcrypt('password'),
            'telefono' => '1234567890',
            'dni' => '456',
            'rol' => 'moderador',
        ]);

        User::factory()->create([
            'name' => 'cliente',
            'email' => 'cliente@gmail.com',
            'password' => bcrypt('password'),
            'telefono' => '1234567890',
            'dni' => '789',
            'rol' => 'cliente',
        ]);

        User::factory()->create([
            'name' => 'Mora Gentil',
            'email' => 'moragentil@gmail.com',
            'password' => bcrypt('12345678'),
            'telefono' => '1234567890',
            'dni' => '12345678',
            'rol' => 'cliente',
        ]);
    }
}
