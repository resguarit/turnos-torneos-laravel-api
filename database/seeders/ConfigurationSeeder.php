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
                'primary' => '#fc5414',
                'secondary' => '#000000',
            ],
            'habilitar_turnos' => false,
            'habilitar_mercado_pago' => false,
            'mercado_pago_access_token' => null,
            'mercado_pago_webhook_secret' => null,
            'nombre_complejo' => 'Rock & Gol',
            'direccion_complejo' => '520 esq. 20 - La Plata, Buenos Aires',
            'telefono_complejo' => '542215395987',
        ]);
    }
}
