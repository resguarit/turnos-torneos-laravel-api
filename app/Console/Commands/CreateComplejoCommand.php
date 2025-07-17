<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Complejo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CreateComplejoCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'complejos:create';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Crea un nuevo complejo de forma interactiva, registrándolo en la base de datos central.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('--- Asistente de Creación de Nuevo Complejo ---');
        $this->comment('Este comando registrará un nuevo complejo en la base de datos central.');
        $this->newLine();

        // --- Recolección de Datos ---
        $nombre = $this->ask('Nombre del complejo (ej: Fut Gol)');
        
        // Validación para el subdominio
        $subdominio = $this->askValid('Subdominio (usado en la URL, ej: futgol)', 'subdominio', ['required', 'string', 'unique:complejos,subdominio']);

        $this->comment('Ahora, los datos de la base de datos para este complejo se completan automaticamente...');
        
        $centralConnection = config('database.connections.mysql_central');
        $db_host = $centralConnection['host'];
        $db_port = $centralConnection['port'];
        $db_database = 'turnos_' . strtolower($subdominio);
        $db_username = $centralConnection['username'];
        $db_password = $centralConnection['password'];

        // --- Confirmación ---
        $this->newLine();
        $this->info('Por favor, confirma los datos para el registro:');
        $this->table(
            ['Campo', 'Valor'],
            [
                ['Nombre', $nombre],
                ['Subdominio', $subdominio],
                ['DB Host', $db_host],
                ['DB Port', $db_port],
                ['DB Nombre', "<fg=yellow>{$db_database}</>"],
                ['DB Usuario', $db_username],
            ]
        );

        if (!$this->confirm('¿Son correctos estos datos?', true)) {
            $this->error('Operación cancelada.');
            return 1;
        }

        try {
        // --- Creación del Registro en la BD Central usando create() ---
        Complejo::create([
            'nombre' => $nombre,
            'subdominio' => $subdominio,
            'db_host' => $db_host,
            'db_port' => $db_port,
            'db_database' => $db_database,
            'db_username' => $db_username,
            'db_password' => $db_password, // Eloquent se encarga de encriptar esto gracias al 'cast'
        ]);

        } catch (\Exception $e) {
            $this->error('¡Ocurrió un error! No se pudo crear el registro del complejo.');
            $this->error($e->getMessage());
            return 1;
        }
            
        // --- Feedback Final ---
        $this->newLine();
        $this->info("✅ ¡Registro para el complejo '{$nombre}' creado con éxito!");
        $this->comment("Asegúrate de que la base de datos '{$db_database}' y el usuario '{$db_username}' existan y tengan los permisos correctos.");
        $this->warn("El siguiente paso es correr las migraciones: php artisan complejos:migrate");
        
        return 0;
    }

    protected function askValid(string $question, string $field, array $rules)
    {
        do {
            $value = $this->ask($question);
            $validator = \Illuminate\Support\Facades\Validator::make([$field => $value], [$field => $rules]);
            if ($validator->fails()) {
                $this->error($validator->errors()->first($field));
            }
        } while ($validator->fails());

        return $value;
    }
}
