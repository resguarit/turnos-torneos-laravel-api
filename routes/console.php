<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();


Artisan::command('bloqueos:clear-expired', function () {
    \App\Models\BloqueoTemporal::where('expira_en', '<', now())->delete();
    $this->info('Bloqueos expirados eliminados.');
})->weekly(); // Intervalo más corto para pruebas

//FALTA HACER EL CRONTAB EN EL SERVIDOR



