<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Equipo;
use App\Models\Jugador;
use Faker\Factory as Faker;

class EquiposYJugadoresSeeder extends Seeder
{
    public function run()
    {
        $faker = Faker::create('es_ES');

        // Generar 20 equipos
        for ($i = 1; $i <= 20; $i++) {
            $equipo = Equipo::create([
                'nombre' => $faker->company,
                'escudo' => null // Puedes agregar una URL de escudo por defecto si lo deseas
            ]);

            // Generar 7 jugadores para cada equipo
            for ($j = 1; $j <= 7; $j++) {
                Jugador::create([
                    'nombre' => $faker->firstName,
                    'apellido' => $faker->lastName,
                    'dni' => $faker->unique()->numberBetween(10000000, 99999999),
                    'telefono' => $faker->phoneNumber,
                    'fecha_nacimiento' => $faker->dateTimeBetween('-40 years', '-18 years')->format('Y-m-d'),
                    'equipo_id' => $equipo->id
                ]);
            }
        }
    }
} 