<?php

namespace App\Services\Interface;

interface AuthServiceInterface 
{
    public function login(array $credentials);
    public function register(array $data);
    public function logout($user);
}