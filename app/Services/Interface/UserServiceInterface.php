<?php

namespace App\Services\Interface;

use Illuminate\Http\Request;

interface UserServiceInterface
{
    public function createUser(array $data);
    public function getUsers($request);
    public function show($id);
    public function update($id, array $data);
    public function destroy($id);
    public function index(Request $request);
}
