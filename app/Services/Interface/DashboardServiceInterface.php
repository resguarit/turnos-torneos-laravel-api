<?php

namespace App\Services\Interface;

interface DashboardServiceInterface
{
    public function totalReservas();
    public function usuariosActivos();
    public function ingresos();
    public function tasaOcupacion();
    public function canchaMasPopular();
    public function horasPico();
    public function reservasPorMes();
}