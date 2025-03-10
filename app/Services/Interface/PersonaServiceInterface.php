<?php

namespace App\Services\Interface;

use Illuminate\Http\Request;

interface PersonaServiceInterface
{
    public function createPersona(Request $request);
    public function updatePersona(Request $request, $id);
    public function deletePersona($id);
    public function restorePersona($id);
    public function showPersona($id);
    public function getPersonas(Request $request);
}