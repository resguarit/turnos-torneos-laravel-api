<?php

namespace Database\Seeders\Complejos;

use Illuminate\Database\Seeder;
use App\Models\Cancha;
use App\Models\Horario;
use Illuminate\Support\Facades\DB;

class CanchasYHorariosSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Limpiar tablas si es necesario (opcional, pero recomendado para desarrollo)
        // DB::table('horarios')->delete();
        // DB::table('canchas')->delete();

        // IDs de los deportes (asumiendo que existen en tu tabla deportes)
        $deporteFutbol5Id = 1;
        $deporteFutbol7Id = 2;

        // --- CANCHAS ---

        // Canchas para Fútbol (Deporte ID: $deporteFutbolId)
        Cancha::create([
            'nro' => 1,
            'descripcion' => 'Cancha Fútbol 5 Techada',
            'tipo_cancha' => 'F5',
            'activa' => true,
            'precio_por_hora' => 55000.00,
            'seña' => 10000.00,
            'deporte_id' => $deporteFutbol5Id
        ]);

        Cancha::create([
            'nro' => 2,
            'descripcion' => 'Cancha Fútbol 7 Descubierta',
            'tipo_cancha' => 'F7',
            'activa' => true,
            'precio_por_hora' => 60000.00,
            'seña' => 10000.00,
            'deporte_id' => $deporteFutbol7Id
        ]);
        
        Cancha::create([
            'nro' => 3,
            'descripcion' => 'Cancha Fútbol 5',
            'tipo_cancha' => 'F5',
            'activa' => true,
            'precio_por_hora' => 55000.00,
            'seña' => 10000.00,
            'deporte_id' => $deporteFutbol5Id
        ]);


        // --- HORARIOS ---
        // Horarios para Fútbol (Deporte ID: $deporteFutbolId)
        $diasSemana = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];
        $horasFutbol = ['18:00:00', '19:00:00', '20:00:00', '21:00:00', '22:00:00', '23:00:00']; // Hora de inicio

        foreach ($diasSemana as $dia) {
            foreach ($horasFutbol as $horaInicio) {
                // Calcular hora_fin sumando una hora a hora_inicio
                $horaFin = date('H:i:s', strtotime($horaInicio . ' +1 hour'));
                Horario::create([
                    'hora_inicio' => $horaInicio,
                    'hora_fin' => $horaFin,
                    'dia' => $dia,
                    'activo' => true,
                    'deporte_id' => $deporteFutbol5Id
                ]);
            }
        }

        foreach ($diasSemana as $dia) {
            foreach ($horasFutbol as $horaInicio) {
                 // Calcular hora_fin sumando una hora a hora_inicio
                $horaFin = date('H:i:s', strtotime($horaInicio . ' +1 hour'));
                Horario::create([
                    'hora_inicio' => $horaInicio,
                    'hora_fin' => $horaFin,
                    'dia' => $dia,
                    'activo' => true,
                    'deporte_id' => $deporteFutbol7Id
                ]);
            }
        }
        
        $this->command->info('Tabla de canchas y horarios poblada con datos de ejemplo para dos deportes.');
    }
} 