<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Services\Interface\ConfigServiceInterface;

class ConfigController extends Controller
{
    protected $configService;

    public function __construct(ConfigServiceInterface $configService)
    {
        $this->configService = $configService;
    }

    public function configurarHorarios(Request $request)
    {
        return $this->configService->configurarHorarios($request);
    }
    public function setHorarioSemanaCompleta(Request $request)
    {
        return $this->configService->setHorarioSemanaCompleta(
            $request->input('hora_apertura'),
            $request->input('hora_cierre'),
            $request->input('deporte_id')
        );
    }
}