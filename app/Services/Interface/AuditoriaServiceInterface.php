<?php

namespace App\Services\Interface;

interface AuditoriaServiceInterface
{
    /**
     * Registra una acción de auditoría en el sistema
     *
     * @param string $accion Acción realizada (crear, modificar, eliminar)
     * @param string $entidad Tipo de entidad afectada (turnos, canchas, horarios, etc.)
     * @param int|null $entidad_id ID de la entidad afectada
     * @param array|null $datos_antiguos Datos anteriores a la modificación
     * @param array|null $datos_nuevos Datos nuevos después de la modificación
     * @return void
     */
    public static function registrar(string $accion, string $tabla, int $id, ?array $datos_anteriores, ?array $datos_nuevos);
 
    /**
     * Obtiene las auditorías filtradas según los criterios especificados
     *
     * @param array $filtros Criterios de filtrado (entidad, accion, fecha_desde, fecha_hasta, usuario_id)
     * @param int $perPage Cantidad de registros por página
     * @return \Illuminate\Pagination\LengthAwarePaginator
     */
    public function obtenerAuditorias(array $filtros = [], int $perPage = 15);
}