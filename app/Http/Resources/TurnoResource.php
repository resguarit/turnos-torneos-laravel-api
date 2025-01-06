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
                'usuarioID' => $this->usuario->id,
                'nombre' => $this->usuario->name,
                'telefono' => $this->usuario->telefono,
            ],
            'horario' => [
                'horaInicio' => $this->horarioCancha->horario->horaInicio,
                'horaFin' => $this->horarioCancha->horario->horaFin,
            ],
            'cancha' => [
                'nro' => $this->horarioCancha->cancha->nro,
                'tipoCancha' => $this->horarioCancha->cancha->tipoCancha,
            ],
            'fecha_reserva' => $this->fecha_reserva,
            'fecha_turno' => $this->fecha_turno->format('Y-m-d'),
            'monto_total' => $this->monto_total,
            'monto_seña' => $this->monto_seña,
            'estado' => $this->estado,
        ];
    }
}
