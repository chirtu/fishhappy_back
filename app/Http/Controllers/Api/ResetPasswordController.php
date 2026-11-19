<?php

namespace App\Http\Controllers\Api;

use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;

class ResetPasswordController
{
    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'token' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'max:72', 'confirmed'],
        ]);

        $status = Password::broker('customers')->reset($data, function (Customer $customer, string $password) {
            DB::transaction(function () use ($customer, $password) {
                $customer->update(['password' => $password]);
                $customer->tokens()->update(['revoked' => true]);
            });
        });

        abort_unless($status === Password::PASSWORD_RESET, 422, 'Invalid or expired reset code.');

        return response()->json(['message' => 'Password reset.']);
    }
}
