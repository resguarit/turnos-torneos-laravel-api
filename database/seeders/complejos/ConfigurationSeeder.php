<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Configuracion;

class ConfigurationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Configuracion::updateOrCreate(
            [
                'id' => 1,
            ],
            [
            'colores' => [
                'primary' => '#000000',
                'secondary' => '#000000',
            ],
            'habilitar_turnos' => true,
            'habilitar_mercado_pago' => false,
            'mercado_pago_access_token' => null,
            'mercado_pago_webhook_secret' => null,
            'nombre_complejo' => 'Rock & Gol',
            'direccion_complejo' => 'Calle 47 Nº 537 entre 5 y 6, La Plata, Buenos Aires',
            'telefono_complejo' => '2215607115',
        ]);
    }
}
