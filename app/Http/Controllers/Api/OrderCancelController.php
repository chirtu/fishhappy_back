<?php

namespace App\Http\Controllers\Api;

use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderCancelController
{
    public function __invoke(Request $request, Order $order): JsonResponse
    {
        abort_unless($order->customer_id === $request->user()->id, 404);
        $changed = Order::whereKey($order->id)->where('status', 'pending')
            ->update(['status' => 'cancelled']);
        abort_unless($changed || $order->refresh()->status === 'cancelled', 409, 'This order can no longer be updated.');

        return response()->json(['message' => 'Cancelled']);
    }
}
