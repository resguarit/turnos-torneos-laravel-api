<?php

namespace App\Notifications\Traits;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use App\Models\Complejo;

trait TenantAware
{
    public ?string $subdominio = null;

    /**
     * Guarda el subdominio del tenant actual en la notificación.
     *
     * @return $this
     */
    public function withTenant(): self
    {
        // Intentamos obtener el subdominio del request actual.
        // Si no estamos en un request (ej. en un Job), se debe setear manualmente.
        if (request() && request()->hasHeader('x-complejo')) {
            $this->subdominio = request()->header('x-complejo');
        }

        return $this;
    }

    /**
     * Helper para generar una URL del frontend para este tenant.
     *
     * @param string $path
     * @return string
     */
    public function tenantUrl(string $path = ''): string
    {
        if (!$this->subdominio) {
            // Fallback a la URL por defecto si no hay subdominio.
            // Es importante tener un valor en APP_URL_FRONT en el .env
            return config('app.url_front') . ($path ? '/' . ltrim($path, '/') : '');
        }

        $baseDomain = config('app.base_domain', 'rgturnos.com.ar');
        $protocol = config('app.url_protocol', 'https');

        $path = $path ? '/' . ltrim($path, '/') : '';

        return "{$protocol}://{$this->subdominio}.{$baseDomain}{$path}";
    }

    public function switchToTenant(): void
    {
        if (!$this->subdominio) {
            return;
        }

        $complejo = Complejo::where('subdominio', $this->subdominio)->first();

        if ($complejo) {
            DB::purge('mysql_tenant');
            Config::set('database.connections.mysql_tenant.host', $complejo->db_host);
            Config::set('database.connections.mysql_tenant.database', $complejo->db_database);
            Config::set('database.connections.mysql_tenant.username', $complejo->db_username);
            Config::set('database.connections.mysql_tenant.password', $complejo->db_password);
            Config::set('database.connections.mysql_tenant.port', $complejo->db_port);
            Config::set('database.default', 'mysql_tenant');
        }
    }
}
