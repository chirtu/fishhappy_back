<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CustomerController
{
    public function __invoke(Request $request): JsonResponse
    {
        return response()->json($request->user()->load(['orders.products', 'favorites']));
    }

    public function update(Request $request): JsonResponse
    {
        $customer = $request->user();
        $customer->update($request->validate([
            'name' => ['required', 'string', 'min:2', 'max:50'],
            'email' => ['required', 'email', 'max:255', Rule::unique('customers')->ignore($customer)],
            'phone' => ['required', 'string', 'max:50'],
        ]));

        return response()->json($customer);
    }

    public function updateFCMToken(Request $request): JsonResponse
    {
        $data = $request->validate(['fcm_token' => ['present', 'nullable', 'string', 'max:4096']]);
        $request->user()->update(['device_id' => $data['fcm_token']]);

        return response()->json(['fcm_token' => $data['fcm_token']]);
    }

    public function favorites(Request $request): JsonResponse
    {
        return response()->json($request->user()->favorites()->get());
    }
}
