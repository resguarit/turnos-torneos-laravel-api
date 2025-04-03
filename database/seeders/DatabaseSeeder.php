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

        User::factory()->create([
            'email' => 'admin@gmail.com',
            'dni' => '123456',
            'password' => bcrypt('password'),
            'rol' => 'admin',
            'persona_id' => $persona->id,
        ]);

        $persona = Persona::create([
            'name' => 'moderador',
            'dni' => '654321',
            'telefono' => '1234567890',
        ]);

        CuentaCorriente::create([
            'persona_id' => $persona->id,
            'saldo' => 0,
        ]);

        User::factory()->create([
            'email' => 'moderador@gmail.com',
            'dni' => '654321',
            'password' => bcrypt('password'),
            'rol' => 'moderador',
            'persona_id' => $persona->id,
        ]);

        $persona = Persona::create([
            'name' => 'cliente',
            'dni' => '87654321',
            'telefono' => '1234567890',
        ]);

        CuentaCorriente::create([
            'persona_id' => $persona->id,
            'saldo' => 0,
        ]);

        User::factory()->create([
            'email' => 'cliente@gmail.com',
            'dni' => '87654321',
            'password' => bcrypt('password'),
            'rol' => 'cliente',
            'persona_id' => $persona->id,
        ]);

        $persona = Persona::create([
            'name' => 'Mora Gentil',
            'dni' => '45396791',
            'telefono' => '1234567890',
        ]);

        CuentaCorriente::create([
            'persona_id' => $persona->id,
            'saldo' => 0,
        ]);

        User::factory()->create([
            'email' => 'moragentil@gmail.com',
            'dni' => '45396791',
            'password' => bcrypt('12345678'),
            'rol' => 'cliente',
            'persona_id' => $persona->id,
        ]);
    }
}
