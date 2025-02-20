<?php

namespace App\Services\Interface;

interface UserServiceInterface
{
    public function createUser(array $data);
    public function getUsers($request);
    public function show($id);
    public function update($id, array $data);
    public function destroy($id);
}
