<?php

namespace App\Http\Controllers\Api;

use App\Helper\FirebaseHelper;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class OrdersController
{
    use FirebaseHelper;

    public function index(Request $request): JsonResponse
    {
        return response()->json($request->user()->orders()->with('products')->latest('id')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $customer = $request->user();
        $data = $request->validate([
            'products' => ['required', 'array', 'min:1'],
            'products.*.id' => ['required', 'integer', 'distinct', Rule::exists('products', 'id')],
            'products.*.quantity' => ['required', 'integer', 'min:1', 'max:999999'],
            'shipping_id' => ['nullable', 'integer', Rule::exists('addresses', 'id')->where('customer_id', $customer->id)],
        ]);

        $order = DB::transaction(function () use ($customer, $data) {
            $order = $customer->orders()->create(['status' => 'pending', 'shipping_id' => $data['shipping_id'] ?? null]);
            foreach ($data['products'] as $product) {
                $order->products()->attach($product['id'], ['quantity' => $product['quantity']]);
            }

            return $order->load('products');
        });

        $this->pushNotification([$customer->device_id], [
            'title' => 'Order placed successfully',
            'message' => 'Your order is being processed.',
        ]);

        return response()->json($order);
    }

    public function show(Request $request, Order $order): JsonResponse
    {
        abort_unless($order->customer_id === $request->user()->id, 404);

        return response()->json($order->load('products'));
    }
}
