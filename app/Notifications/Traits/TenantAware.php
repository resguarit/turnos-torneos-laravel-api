<?php

namespace App\Notifications\Traits;

trait TenantAware
{
    public ?string $subdominio;

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
}
