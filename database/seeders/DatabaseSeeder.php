<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;   
use App\Models\Persona;
use App\Models\CuentaCorriente;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        DB::transaction(function () {

            //creamos las personas
            $persona = Persona::firstOrCreate([
                'dni' => '123456',
            ], [
                'name' => 'Admin',
                'telefono' => '123456789'
            ]);

            CuentaCorriente::firstOrCreate([
                'persona_id' => $persona->id,
            ], [
                'saldo' => 0,
            ]);

            User::firstOrCreate(
                ['email' => 'admin@gmail.com'],
                [
                    'dni' => $persona->dni,
                    'password' => bcrypt('password'),
                    'rol' => 'admin',
                    'persona_id' => $persona->id,
                ]);

            $this->call([
                ConfigurationSeeder::class,
            ]);
        });
    }
}
