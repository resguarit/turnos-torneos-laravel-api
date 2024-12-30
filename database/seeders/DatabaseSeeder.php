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

        /**User::factory()->create([
            'name' => 'Test',
            'email' => 'test@gmail.com',
            'password' => bcrypt('password'),
            'telefono' => '1234567890',
        ]);**/

        User::factory()->create([
            'name' => 'Pedro Ramirez',
            'email' => 'pedro@gmail.com',
            'password' => bcrypt('password1'),
            'telefono' => '2214545',
        ]);
    }
}
