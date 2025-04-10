<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Persona;
use App\Models\CuentaCorriente;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        //creamos las personas
        $persona = Persona::create([
            'name' => 'admin',
            'dni' => '123456',
            'telefono' => '1234567890',
        ]);

        CuentaCorriente::create([
            'persona_id' => $persona->id,
            'saldo' => 0,
        ]);

        User::create([
            'email' => 'admin@gmail.com',
            'dni' => '123456',
            'password' => bcrypt('password'),
            'rol' => 'admin',
            'persona_id' => $persona->id,
        ]);
        
    }
}
