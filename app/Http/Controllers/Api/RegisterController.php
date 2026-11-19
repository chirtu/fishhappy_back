<?php

namespace App\Http\Controllers\Api;

use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RegisterController
{
    public function register(Request $request): JsonResponse
    {
        $data = Customer::validate($request->all())->validate();
        $token = DB::transaction(function () use ($data) {
            return Customer::create($data)->createToken('fish-happy-token')->accessToken;
        });

        return response()->json(['token' => $token], 201);
    }
}
