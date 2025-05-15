<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Enums\TurnoEstado;

class TurnoResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = [
            'id' => $this->id,
            'usuario' => [
                'usuario_id' => $this->persona?->usuario?->id ?? null,
                'persona_id' => $this->persona?->id ?? null,
                'nombre' => $this->persona?->name ?? 'Sin nombre',
                'dni' => $this->persona?->dni ?? '',
                'telefono' => $this->persona?->telefono ?? '',
                'email' => $this->persona?->usuario?->email ?? 'Sin email',
            ],
            'horario' => [
                'hora_inicio' => $this->horario->hora_inicio,
                'hora_fin' => $this->horario->hora_fin,
            ],
            'cancha' => [
                'nro' => $this->cancha->nro,
                'tipo_cancha' => $this->cancha->tipo_cancha,
                'deporte' => $this->cancha->deporte ? [
                    'id' => $this->cancha->deporte->id,
                    'nombre' => $this->cancha->deporte->nombre,
                    'jugadores_por_equipo' => $this->cancha->deporte->jugadores_por_equipo,
                ] : null,
            ],
            'motivo_cancelacion' => $this->when($this->estado === TurnoEstado::CANCELADO, function () {
                return $this->motivo_cancelacion ? $this->motivo_cancelacion->motivo : null;
            }),
            'fecha_reserva' => $this->fecha_reserva,
            'fecha_turno' => $this->fecha_turno->format('Y-m-d'),
            'monto_total' => $this->monto_total,
            'monto_seña' => $this->monto_seña,
            'estado' => $this->estado,
            'tipo' => $this->tipo,
            'created_at' => $this->created_at,
        ];

        if ($this->tipo === 'torneo' && $this->partido) {
            // Forzamos a obtener el modelo, no el string
            $fecha = $this->partido->fecha instanceof \App\Models\Fecha
                ? $this->partido->fecha
                : ($this->partido->fecha_id ? \App\Models\Fecha::find($this->partido->fecha_id) : null);

            $zona = $fecha && $fecha->zona ? $fecha->zona : null;
            $torneo = $zona && $zona->torneo ? $zona->torneo : null;

            $data['partido'] = [
                'id' => $this->partido->id,
                'fecha' => $fecha ? [
                    'id' => $fecha->id,
                    'nombre' => $fecha->nombre ?? null,
                    'zona' => $zona ? [
                        'id' => $zona->id,
                        'nombre' => $zona->nombre,
                        'torneo' => $torneo ? [
                            'id' => $torneo->id,
                            'nombre' => $torneo->nombre,
                        ] : null,
                    ] : null,
                ] : null,
                'equipos' => [
                    'local' => $this->partido->equipoLocal ? [
                        'id' => $this->partido->equipoLocal->id,
                        'nombre' => $this->partido->equipoLocal->nombre,
                    ] : null,
                    'visitante' => $this->partido->equipoVisitante ? [
                        'id' => $this->partido->equipoVisitante->id,
                        'nombre' => $this->partido->equipoVisitante->nombre,
                    ] : null,
                ],
            ];
        }

        return $data;
    }
}
