<?php

namespace App\Services\Implementation;

use App\Models\Auditoria;
use App\Services\Interface\AuditoriaServiceInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class AuditoriaService implements AuditoriaServiceInterface
{
    public static function registrar(string $accion, string $tabla, int $id, ?array $datos_anteriores, ?array $datos_nuevos)
    {
        // Usar el guardia correspondiente (ej: sanctum para APIs)
        $usuario_id = Auth::guard('sanctum')->id();
        
        if (!$usuario_id) {
            // Opcional: manejar casos donde no hay usuario autenticado
            return;
        }
    
        Auditoria::create([
            'usuario_id' => $usuario_id,
            'accion' => $accion,
            'entidad' => $tabla,
            'entidad_id' => $id,
            'datos_antiguos' => $datos_anteriores ?? null,
            'datos_nuevos' => $datos_nuevos ?? null,
            'ip' => Request::ip(),
            'user_agent' => Request::userAgent(),
            'fecha_accion' => now()
        ]);
    }

    public function obtenerAuditorias(array $filtros = [], int $perPage = 15)
    {
        $query = Auditoria::with('usuario.persona')->latest('fecha_accion');
        
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