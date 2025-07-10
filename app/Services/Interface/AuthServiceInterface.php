<?php

namespace App\Services\Interface;

interface AuthServiceInterface 
{
    public function login(array $credentials);
    public function register(array $data, $subdominio);
    public function logout($user);
}