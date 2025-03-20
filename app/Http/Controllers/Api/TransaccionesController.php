<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\Interface\TransaccionServiceInterface;
use Illuminate\Support\Facades\Auth;

class TransaccionesController extends Controller
{
    protected $transaccionService;

    public function __construct(TransaccionServiceInterface $transaccionService)
    {
        $this->transaccionService = $transaccionService;
    }

    public function index(Request $request)
    {
        return $this->transaccionService->getTransacciones($request);
    }

    public function store(Request $request)
    {
        return $this->transaccionService->storeTransaccion($request);
    }
}