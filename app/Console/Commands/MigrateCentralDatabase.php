<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class MigrateCentralDatabase extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'migrate:central';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Corre migraciones para la base de datos central';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Iniciando migraciones para la base de datos central...');

        // Ejecutar las migraciones
        Artisan::call('migrate', [
            '--database' => 'mysql_central',
            '--path' => 'database/migrations/central',
            '--force' => true,
        ]);

        $this->info(Artisan::output());
        $this->info('Migraciones completadas exitosamente para la base de datos central.');
    }
}
