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
            'dni' => '45396791',
            'password' => bcrypt('password'),
            'telefono' => '1234567890',
            'rol' => 'admin',
        ]);

        User::factory()->create([
            'name' => 'moderador',
            'email' => 'moderador@gmail.com',
            'dni' => '11223344',
            'password' => bcrypt('password'),
            'telefono' => '1234567890',
            'rol' => 'moderador',
        ]);

        User::factory()->create([
            'name' => 'cliente',
            'email' => 'cliente@gmail.com',
            'dni' => '87654321',
            'password' => bcrypt('password'),
            'telefono' => '1234567890',
            'rol' => 'cliente',
        ]);

        User::factory()->create([
            'name' => 'Mora Gentil',
            'email' => 'moragentil@gmail.com',
            'dni' => '12345678',
            'password' => bcrypt('12345678'),
            'telefono' => '1234567890',
            'rol' => 'cliente',
        ]);
    }
}
