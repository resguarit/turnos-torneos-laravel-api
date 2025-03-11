<?php

namespace App\Services\Implementation;

use App\Models\Auditoria;
use App\Services\Interface\AuditoriaServiceInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request;

class AuditoriaService implements AuditoriaServiceInterface
{
    public function registrar(string $accion, string $entidad, ?int $entidad_id = null, ?array $datos_antiguos = null, ?array $datos_nuevos = null, ?int $usuario_id_forzado = null)
    {
        try {
            $usuario_id = $usuario_id_forzado ?? Auth::id();

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
        } catch (\Exception $e) {
            Log::error("Error al registrar auditoría: " . $e->getMessage());
            Log::error($e->getTraceAsString());
        }
    }

    public function obtenerAuditorias(array $filtros = [], int $perPage = 15)
    {
        $query = Auditoria::with('usuario')->latest('fecha_accion');
        
        if (isset($filtros['tipo']) && !empty($filtros['tipo'])) {
            $query->where('accion', $filtros['tipo']);
        }
        
        if (isset($filtros['usuario']) && !empty($filtros['usuario'])) {
            $query->whereHas('usuario', function ($q) use ($filtros) {
                $q->where('name', 'like', '%' . $filtros['usuario'] . '%')
                  ->orWhere('id', $filtros['usuario']);
            });
        }
        
        if (isset($filtros['fecha_inicio']) && !empty($filtros['fecha_inicio'])) {
            $query->whereDate('fecha_accion', '>=', $filtros['fecha_inicio']);
        }
        
        if (isset($filtros['fecha_fin']) && !empty($filtros['fecha_fin'])) {
            $query->whereDate('fecha_accion', '<=', $filtros['fecha_fin']);
        }
        
        return $query->paginate($perPage);
    }
}