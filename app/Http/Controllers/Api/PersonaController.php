<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\Interface\PersonaServiceInterface;
use Illuminate\Support\Facades\Auth;

class PersonaController extends Controller
{
    protected $personaService;

    public function __construct(PersonaServiceInterface $personaService)
    {
        $this->personaService = $personaService;
    }

    public function index(Request $request)
    {
        return $this->personaService->getPersonas($request);
    }

    public function store(Request $request)
    {
        return $this->personaService->createPersona($request);
    }

    public function update(Request $request, $id)
    {
        return $this->personaService->updatePersona($request, $id);
    }
}
