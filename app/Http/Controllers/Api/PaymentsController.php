<?php

namespace App\Http\Controllers\Api;

use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PaymentsController
{
    public function __invoke(Request $request, Order $order): JsonResponse
    {
        $customer = $request->user();
        abort_unless($order->customer_id === $customer->id, 404);
        abort_unless(in_array($order->status, ['pending', 'confirmed'], true), 409, 'This order cannot be paid.');

        $notificationId = config('services.pesapal.notification_id');
        abort_unless($notificationId, 503, 'Payment is not configured.');

        $lock = Cache::lock("payment:order:{$order->id}", 45);
        abort_unless($lock->get(), 409, 'A payment request is in progress.');

        try {
            abort_if(Payment::where('order_id', $order->id)->where('status', 'COMPLETED')->exists(), 409, 'This order is already paid.');
            $active = Payment::where('order_id', $order->id)->where('status', 'PENDING')->latest('id')->first();
            if ($active && $active->redirect_url) {
                return response()->json(['url' => $active->redirect_url]);
            }

            $amount = round($order->products->sum(fn ($product) => $product->price * $product->pivot->quantity), 2);
            abort_unless($amount > 0, 422, 'This order has no payable balance.');

            $payment = Payment::create([
                'customer_id' => $customer->id,
                'order_id' => $order->id,
                'transaction_id' => Str::uuid()->toString(),
                'status' => 'NEW',
                'payment_method' => 'PESAPAL',
                'amount' => $amount,
            ]);

            [$firstName, $lastName] = array_pad(explode(' ', $customer->name, 2), 2, '');
            $result = $this->request('POST', 'Transactions/SubmitOrderRequest', [
                'id' => $payment->transaction_id,
                'currency' => 'TZS',
                'amount' => $amount,
                'description' => "Fish Happy order #{$order->id}",
                'callback_url' => route('payments.pesapal.callback'),
                'notification_id' => $notificationId,
                'billing_address' => [
                    'email_address' => $customer->email,
                    'phone_number' => $customer->phone,
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'country_code' => 'TZ',
                ],
            ]);
            $url = $result['redirect_url'] ?? '';
            $trackingId = $result['order_tracking_id'] ?? '';
            abort_unless(filter_var($url, FILTER_VALIDATE_URL) && parse_url($url, PHP_URL_SCHEME) === 'https' && $trackingId, 502, 'Payment provider did not return a checkout link.');
            $payment->update(['tracking_id' => $trackingId, 'status' => 'PENDING', 'redirect_url' => $url]);

            return response()->json(['url' => $url]);
        } finally {
            $lock->release();
        }
    }

    public function callback(Request $request): View
    {
        return view('payments.result', ['status' => $this->refreshPayment($request)]);
    }

    public function ipn(Request $request): JsonResponse
    {
        $this->refreshPayment($request);

        return response()->json([
            'orderNotificationType' => $request->input('OrderNotificationType'),
            'orderTrackingId' => $request->input('OrderTrackingId'),
            'orderMerchantReference' => $request->input('OrderMerchantReference'),
            'status' => 200,
        ]);
    }

    private function refreshPayment(Request $request): string
    {
        $data = $request->validate([
            'OrderMerchantReference' => ['required', 'string', 'max:255'],
            'OrderTrackingId' => ['required', 'string', 'max:255'],
        ]);
        $payment = Payment::where('transaction_id', $data['OrderMerchantReference'])
            ->where('tracking_id', $data['OrderTrackingId'])->firstOrFail();
        $result = $this->request('GET', 'Transactions/GetTransactionStatus', ['orderTrackingId' => $payment->tracking_id]);

        abort_unless(($result['merchant_reference'] ?? null) === $payment->transaction_id, 502, 'Payment reference mismatch.');

        $status = strtoupper((string) ($result['payment_status_description'] ?? ''));
        if ($status === 'COMPLETED') {
            abort_unless(($result['currency'] ?? null) === 'TZS' &&
                is_numeric($result['amount'] ?? null) &&
                round((float) $result['amount'], 2) === (float) $payment->amount, 502, 'Payment amount mismatch.');
        }
        if (in_array($status, ['COMPLETED', 'FAILED', 'REVERSED'], true)) {
            $update = Payment::whereKey($payment->id);
            if ($status !== 'REVERSED') {
                $update->whereNotIn('status', ['COMPLETED', 'REVERSED']);
            }
            $update->update(['status' => $status]);
        }

        return $payment->refresh()->status;
    }

    private function request(string $method, string $path, array $data): array
    {
        $settings = config('services.pesapal');
        abort_unless($settings['key'] && $settings['secret'], 503, 'Payment is not configured.');

        try {
            $client = Http::baseUrl(rtrim($settings['url'], '/'))->acceptJson()->connectTimeout(4)->timeout(12);
            $auth = $client->post('Auth/RequestToken', [
                'consumer_key' => $settings['key'],
                'consumer_secret' => $settings['secret'],
            ]);
            abort_unless($auth->successful() && is_string($auth->json('token')), 502, 'Could not authenticate with the payment provider.');
            $client->withToken($auth->json('token'));
            $result = $method === 'POST' ? $client->post($path, $data) : $client->get($path, $data);
        } catch (ConnectionException) {
            abort(502, 'Could not connect to the payment provider.');
        }

        abort_unless($result->successful() && is_array($result->json()) && ! $result->json('error.code') && ! $result->json('error.message'), 502, 'Payment provider could not complete the request.');

        return $result->json();
    }
}
