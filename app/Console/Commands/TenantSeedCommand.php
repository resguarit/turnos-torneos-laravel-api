<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Complejo;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Database\Seeders\Complejos\DatabaseSeeder;
use Dflydev\DotAccessData\Data;

class TenantSeedCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'complejos:seed {subdominio?} {--force : Forzar la ejecución sin confirmación}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Ejecuta los seeders para uno o todos los complejos. Deja el subdominio en blanco para ejecutar en todos.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $subdominio = $this->argument('subdominio');

        if ($subdominio) {
            $complejos = Complejo::where('subdominio', $subdominio)->get();
            if ($complejos->isEmpty()) {
                $this->error("No se encontró ningún complejo con el subdominio: {$subdominio}");
                return;
            }
        } else {
            $complejos = Complejo::all();
            if ($complejos->isEmpty()) {
                $this->info('No hay complejos registrados para ejecutar seeders.');
                return;
            }
        }

        $tenantCount = $complejos->count();
        $plural = $tenantCount > 1 ? 's' : '';

        if (!$this->option('force')) {
            if (!$this->confirm("¿Estás seguro de que deseas ejecutar los seeders en {$tenantCount} complejo{$plural}?")) {
                $this->info('Operación cancelada.');
                return;
            }
        }

        foreach ($complejos as $complejo) {
            $this->info("Ejecutando seeders para el complejo: {$complejo->nombre} / subdominio: {$complejo->subdominio}");

            // Configurar la conexión de base de datos del complejo
            DB::purge('mysql_tenant');

            Config::set('database.connections.mysql_tenant.host', $complejo->db_host);
            Config::set('database.connections.mysql_tenant.database', $complejo->db_database);
            Config::set('database.connections.mysql_tenant.username', $complejo->db_username);
            Config::set('database.connections.mysql_tenant.password', $complejo->db_password);
            Config::set('database.connections.mysql_tenant.port', $complejo->db_port);

            // Ejecutar los seeders
            $this->call('db:seed', [
                '--database' => 'mysql_tenant',
                '--force' => true,
                '--class' => DatabaseSeeder::class,
            ]);

            $this->info("Seeders completados exitosamente para el complejo: {$complejo->nombre}");
        }

        $this->info("Todos los seeders se han completado para {$tenantCount} complejo{$plural}.");
        $this->info('Operación finalizada.');
    }
}
