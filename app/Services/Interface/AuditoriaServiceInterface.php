<?php

namespace App\Services\Interface;

interface AuditoriaServiceInterface
{
    public function registrar(string $accion, string $entidad, ?int $entidad_id = null, ?array $datos_antiguos = null, ?array $datos_nuevos = null, ?int $usuario_id_forzado = null);
    public function obtenerAuditorias(array $filtros = [], int $perPage = 15);
}