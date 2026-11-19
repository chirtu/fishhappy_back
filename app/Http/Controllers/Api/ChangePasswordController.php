<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ChangePasswordController
{
    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'old_password' => ['required', 'string', 'current_password:api'],
            'new_password' => ['required', 'string', 'min:8', 'max:72', 'confirmed', 'different:old_password'],
        ]);
        $customer = $request->user();
        DB::transaction(function () use ($customer, $data) {
            $customer->update(['password' => $data['new_password']]);
            $customer->tokens()->whereKeyNot($customer->token()->getKey())->update(['revoked' => true]);
        });

        return response()->json(['message' => 'Password changed.']);
    }
}
