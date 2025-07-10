<?php

namespace Database\Seeders\Complejos;

use Illuminate\Database\Seeder;
use App\Models\Equipo;
use App\Models\Jugador;
use Faker\Factory as Faker;

class EquiposYJugadoresSeeder extends Seeder
{
    public function run()
    {
        $faker = Faker::create('es_ES');
        $equipos = [];

        // Generar 20 equipos
        for ($i = 1; $i <= 20; $i++) {
            $equipos[] = Equipo::create([
                'nombre' => $faker->company . ' FC', // Make names more distinct
                'escudo' => null
            ]);
        }

        // Generar jugadores y assignarlos a equipos
        foreach ($equipos as $equipo) {
            // Generar 7 jugadores para cada equipo
            for ($j = 1; $j <= 7; $j++) {
                $jugador = Jugador::create([
                    'nombre' => $faker->firstName,
                    'apellido' => $faker->lastName,
                    'dni' => $faker->unique()->numerify('########'), // Use numerify for DNI
                    'telefono' => $faker->phoneNumber,
                    'fecha_nacimiento' => $faker->dateTimeBetween('-40 years', '-18 years')->format('Y-m-d'),
                    // No equipo_id here anymore
                ]);
                // Attach the player to the current team
                $jugador->equipos()->attach($equipo->id);
            }
        }
    }
}