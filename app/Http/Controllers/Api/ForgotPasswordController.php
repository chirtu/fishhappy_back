<?php

namespace App\Http\Controllers\Api;

use App\Notifications\CustomerPasswordCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

class ForgotPasswordController
{
    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'email']]);
        Password::broker('customers')->sendResetLink($data, function ($customer, string $token) {
            Notification::route('mail', $customer->email)->notify(
                new CustomerPasswordCode($token),
            );
        });

        return response()->json(['message' => 'If the email matches an account, a reset code will be sent.']);
    }
}
