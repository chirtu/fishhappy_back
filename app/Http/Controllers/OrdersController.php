<?php

namespace App\Http\Controllers;

use App\Helper\FirebaseHelper;
use App\Models\Customer;
use App\Models\Order;
use Illuminate\Http\Request;

class OrdersController extends Controller
{
    use FirebaseHelper;

    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        return view('orders.index', [
            'orders' => Order::latest()->paginate(),
        ]);
    }

    public function show(Order $order)
    {
        return view('orders.show', [
            'order' => $order,
        ]);
    }

    public function updateStatus(Request $request)
    {
        $data = $request->validate(['order' => ['required', 'integer', 'exists:orders,id']]);
        $status = $request->route('status');
        abort_unless(in_array($status, ['pending', 'confirmed', 'cancelled', 'completed'], true), 422);
        $order = Order::findOrFail($data['order']);
        $order->status = $status;
        $order->save();

        $device = Customer::where('id', $order->customer_id)->select('device_id')->first();

        // UPDATE ORDER'S STASTUS NOTIFICATION
        $payload = [];
        $payload['title'] = 'Order Placed Successfully';
        $payload['message'] = 'You can drop any message here';
        $payload['body'] = 'Your order is being '.$request->status.' You will here from us shortly';

        $this->pushNotification([$device->device_id], $payload);

        return redirect()->route('orders.index');
    }
}
