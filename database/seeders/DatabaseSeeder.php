<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;   
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

        $persona = Persona::create([
            'name' => 'Mariano Salas',
            'dni' => '45356347',
            'telefono' => '2215607115'
        ]);

        CuentaCorriente::create([
            'persona_id' => $persona->id,
            'saldo' => 0,
        ]);

        User::create([
            'email' => 'marianosalas24@gmail.com',
            'dni' => '45356347',
            'password' => bcrypt('password'),
            'rol' => 'cliente',
            'persona_id' => $persona->id,
        ]);

        $persona = Persona::create([
            'name' => 'Mariano Admin',
            'dni' => '45356348',
            'telefono' => '2215607116'
        ]);

        CuentaCorriente::create([
            'persona_id' => $persona->id,
            'saldo' => 0,
        ]);

        User::create([
            'email' => 'msalas.escuela@gmail.com',
            'dni' => '45356348',
            'password' => bcrypt('password'),
            'rol' => 'admin',
            'persona_id' => $persona->id,
        ]);

        $this->call([
            EquiposYJugadoresSeeder::class,
            CanchasYHorariosSeeder::class,
        ]);
    }
}
