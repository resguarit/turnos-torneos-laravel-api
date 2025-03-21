<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\MetodoPago;
class MetodosPagoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        MetodoPago::create([
            'nombre' => 'Efectivo',
            'descripcion' => 'Pago en efectivo',
            'activo' => true,
        ]);

        MetodoPago::create([
            'nombre' => 'Tarjeta de Crédito',
            'descripcion' => 'Pago con tarjeta de crédito',
            'activo' => true,
        ]);

        MetodoPago::create([
            'nombre' => 'Mercado Pago',
            'descripcion' => 'Pago por Mercado Pago',
            'activo' => true,
        ]);

        MetodoPago::create([
            'nombre' => 'Transferencia Bancaria',
            'descripcion' => 'Pago por transferencia bancaria',
            'activo' => true,
        ]);
    }
}
