<?php

namespace Database\Seeders\Complejos;

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
            'nombre_complejo' => 'RG Turnos',
            'direccion_complejo' => 'Diagonal 76 Nº 46 entre 14 y 15, La Plata, Buenos Aires',
            'telefono_complejo' => '2216914649',
        ]);
    }
}
