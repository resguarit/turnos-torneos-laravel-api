<?php
// app/Console/Commands/ClearExpiredBloqueos.php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\BloqueoTemporal;

class ClearExpiredBloqueos extends Command
{
    // El nombre del comando
    protected $signature = 'bloqueos:clear-expired';

    // Descripción del comando
    protected $description = 'Eliminar bloqueos temporales expirados';

    public function handle()
    {
        // Lógica para eliminar los bloqueos expirados
        BloqueoTemporal::where('expira_en', '<', now())->delete();

        // Mensaje informativo
        $this->info('Bloqueos expirados eliminados.');
    }
}
