<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use App\Models\Complejo;

class MigrateTenants extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'migrate:complejos';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Corre las migraciones para los complejos';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $complejos = Complejo::all();

        if ($complejos->isEmpty()) {
            $this->info('No hay complejos registrados para migrar.');
            return;
        }

        foreach ($complejos as $complejo) {
            $this->info("Iniciando migraciones para el complejo: {$complejo->nombre} / subdominio: {$complejo->subdominio}");

            DB::purge('mysql_tenant');
            
            Config::set('database.connections.mysql_tenant.host', $complejo->db_host);
            Config::set('database.connections.mysql_tenant.database', $complejo->db_database);
            Config::set('database.connections.mysql_tenant.username', $complejo->db_username);
            Config::set('database.connections.mysql_tenant.password', $complejo->db_password);
            Config::set('database.connections.mysql_tenant.port', $complejo->db_port);

            // Ejecutar las migraciones
            Artisan::call('migrate', [
                '--database' => 'mysql_tenant',
                '--path' => 'database/migrations/complejos',
                '--force' => true,
            ]);

            $this->info(Artisan::output());
            $this->info("Migraciones completadas exitosamente para el complejo: {$complejo->nombre}");
        }

        $this->info('Todas las migraciones de complejos se han completado.');
    }
}
