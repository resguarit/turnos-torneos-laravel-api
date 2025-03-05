<?php

namespace App\Services\Implementation;

use App\Models\Auditoria;
use App\Services\Interface\AuditoriaServiceInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class AuditoriaService implements AuditoriaServiceInterface
{
    public static function registrar(string $accion, string $entidad, ?int $entidad_id = null, ?array $datos_antiguos = null, ?array $datos_nuevos = null)
    {
        $usuario_id = Auth::id();
        
        if (!$usuario_id) {
            // Si no hay usuario autenticado, se podría manejar de otra forma o simplemente no registrar
            return;
        }

        Auditoria::create([
            'usuario_id' => $usuario_id,
            'accion' => $accion,
            'entidad' => $entidad,
            'entidad_id' => $entidad_id,
            'datos_antiguos' => $datos_antiguos ? json_encode($datos_antiguos, JSON_PRETTY_PRINT) : null,
            'datos_nuevos' => $datos_nuevos ? json_encode($datos_nuevos, JSON_PRETTY_PRINT) : null,
            'ip' => Request::ip(),
            'user_agent' => Request::userAgent(),
            'fecha_accion' => now()
        ]);
    }

    public function obtenerAuditorias(array $filtros = [], int $perPage = 15)
    {
        $query = Auditoria::with('usuario')->latest('fecha_accion');
        
        // Filtrado por entidad
        if (isset($filtros['entidad'])) {
            $query->where('entidad', $filtros['entidad']);
        }
        
        // Filtrado por acción
        if (isset($filtros['accion'])) {
            $query->where('accion', $filtros['accion']);
        }
        
        // Filtrado por fecha
        if (isset($filtros['fecha_desde'])) {
            $query->whereDate('fecha_accion', '>=', $filtros['fecha_desde']);
        }
        
        if (isset($filtros['fecha_hasta'])) {
            $query->whereDate('fecha_accion', '<=', $filtros['fecha_hasta']);
        }
        
        // Filtrado por usuario
        if (isset($filtros['usuario_id'])) {
            $query->where('usuario_id', $filtros['usuario_id']);
        }
        
        return $query->paginate($perPage);
    }
}