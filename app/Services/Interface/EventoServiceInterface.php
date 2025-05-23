<?php

namespace App\Services\Interface;

use App\Models\Evento;
use App\Enums\EventoEstado;
use Illuminate\Http\Request;

interface EventoServiceInterface
{
    public function getAll();
    public function getById($id);
    public function create(Request $request);
    public function update(Request $request, $id);
    public function delete($id);
    public function getEventosComoTurnos();
}