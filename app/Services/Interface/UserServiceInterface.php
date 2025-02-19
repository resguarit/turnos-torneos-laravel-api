<?php

namespace App\Services\Interfaces;

interface UserServiceInterface
{
    public function register(array $data);
    public function createUser(array $data);
    public function login(array $credentials);
    public function logout($user);
    public function getUsers($request);
    public function show($id);
    public function update($id, array $data);
    public function destroy($id);
}
