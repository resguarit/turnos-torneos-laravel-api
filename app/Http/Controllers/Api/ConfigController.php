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
}