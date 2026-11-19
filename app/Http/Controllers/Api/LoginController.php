<?php

namespace App\Http\Controllers\Api;

use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class LoginController
{
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);
        $customer = Customer::where('email', $credentials['email'])->first();

        abort_unless($customer && Hash::check($credentials['password'], $customer->password), 401, 'Wrong email or password.');

        return response()->json(['token' => $customer->createToken('fish-happy-token')->accessToken]);
    }
}
