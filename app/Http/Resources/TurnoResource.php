<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TurnoResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'usuario' => [
                'usuario_id' => $this->usuario->id,
                'nombre' => $this->usuario->name,
                'dni' => $this->usuario->dni,
                'telefono' => $this->usuario->telefono,
                'email' => $this->usuario->email,
            ],
            'horario' => [
                'hora_inicio' => $this->horario->hora_inicio,
                'hora_fin' => $this->horario->hora_fin,
            ],
            'cancha' => [
                'nro' => $this->cancha->nro,
                'tipo_cancha' => $this->cancha->tipo_cancha,
            ],
            'fecha_reserva' => $this->fecha_reserva,
            'fecha_turno' => $this->fecha_turno->format('Y-m-d'),
            'monto_total' => $this->monto_total,
            'monto_seña' => $this->monto_seña,
            'estado' => $this->estado,
            'tipo' => $this->tipo,
            'created_at' => $this->created_at,
        ];
    }
}
