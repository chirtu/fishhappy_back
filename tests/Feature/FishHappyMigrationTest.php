<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\District;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Region;
use App\Models\User;
use App\Models\Ward;
use App\Notifications\CustomerPasswordCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Laravel\Passport\ClientRepository;
use Tests\TestCase;

class FishHappyMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_mobile_account_shipping_order_and_payment_flow(): void
    {
        $keys = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($keys, $privateKey);
        config([
            'passport.private_key' => $privateKey,
            'passport.public_key' => openssl_pkey_get_details($keys)['key'],
            'services.pesapal.key' => 'sample',
            'services.pesapal.secret' => 'sample',
            'services.pesapal.notification_id' => 'sample',
        ]);
        app(ClientRepository::class)->createPersonalAccessGrantClient('Fish Happy', 'customers');

        $registration = ['name' => 'Fish Customer', 'email' => 'customer@example.com', 'phone' => '0712345678', 'password' => 'Password123'];
        $this->postJson('/api/cstmr/register', $registration)->assertCreated();
        $token = $this->postJson('/api/cstmr/login', $registration)->assertOk()->json('token');
        $this->withToken($token);

        $category = ProductCategory::create(['name' => 'Fish']);
        $fish = Product::create(['category_id' => $category->id, 'name' => 'Kingfish', 'price' => 3000]);
        $this->getJson('/api/cstmr/products')->assertOk()->assertJsonPath('0.name', 'Kingfish');
        $this->postJson("/api/cstmr/products/{$fish->id}/favourites")->assertOk();
        $this->getJson('/api/cstmr')->assertOk()->assertJsonPath('favorites.0.id', $fish->id)->assertJsonMissingPath('password');
        $this->postJson('/api/cstmr', [...$registration, 'name' => 'New Name'])->assertOk()->assertJsonPath('name', 'New Name');

        $region = Region::create(['name' => 'Region', 'napa_region_id' => 'r1']);
        $district = District::create(['name' => 'District', 'napa_district_id' => 'd1', 'region_id' => $region->id]);
        Ward::create(['name' => 'Ward', 'napa_ward_id' => 'w1', 'region_id' => $region->id, 'district_id' => $district->id]);
        $addressId = $this->postJson('/api/v1/create_shipping', [
            'napa_ward_id' => 'w1',
            'napa_street_id' => 's1',
            'street_name' => 'Street',
            'napa_street_road_id' => 'rd1',
            'street_road_name' => 'Road',
            'contact_name' => 'Fish Customer',
            'phone_number' => '0712345678',
        ])->assertOk()->json('address.id');

        $orderId = $this->postJson("/api/cstmr/orders?shipping_id=$addressId", [
            'products' => [['id' => $fish->id, 'quantity' => 2]],
        ])->assertOk()->assertJsonPath('shipping_id', $addressId)->json('id');

        Http::preventStrayRequests();
        Http::fake([
            '*/Auth/RequestToken' => Http::response(['token' => 'test-token']),
            '*/Transactions/SubmitOrderRequest' => Http::response([
                'redirect_url' => 'https://payments.example.com/order',
                'order_tracking_id' => 'tracking-id',
            ]),
        ]);
        $this->getJson("/api/cstmr/orders/$orderId/payments")->assertOk()->assertJsonPath('url', 'https://payments.example.com/order');
        $this->getJson("/api/cstmr/orders/$orderId")->assertOk()->assertJsonPath('status', 'pending');
        $payment = Payment::where('order_id', $orderId)->firstOrFail();
        Http::fake([
            '*/Transactions/GetTransactionStatus*' => Http::response([
                'merchant_reference' => $payment->transaction_id,
                'currency' => 'TZS',
                'amount' => 6000,
                'payment_status_description' => 'Completed',
                'error' => ['code' => null, 'message' => null],
            ]),
        ]);
        $this->postJson('/api/payments/pesapal/ipn', [
            'OrderMerchantReference' => $payment->transaction_id,
            'OrderTrackingId' => $payment->tracking_id,
            'OrderNotificationType' => 'IPNCHANGE',
        ])->assertOk()->assertJsonPath('status', 200);
        $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'COMPLETED']);

        $newPassword = ['old_password' => 'Password123', 'new_password' => 'NewPassword123', 'new_password_confirmation' => 'NewPassword123'];
        $this->postJson('/api/cstmr/change_password', $newPassword)->assertOk();

        $other = [...$registration, 'email' => 'other@example.com'];
        $otherToken = $this->postJson('/api/cstmr/register', $other)->assertCreated()->json('token');
        $this->app['auth']->forgetGuards();
        $this->withToken($otherToken)->getJson("/api/cstmr/orders/$orderId")->assertNotFound();
    }

    public function test_customer_password_reset_and_admin_views(): void
    {
        Notification::fake();
        $customer = Customer::create(['name' => 'Customer', 'email' => 'customer@example.com', 'phone' => '0712345678', 'password' => 'Original123']);
        $this->postJson('/api/cstmr/password/email', ['email' => $customer->email])->assertOk();
        Notification::assertSentOnDemand(CustomerPasswordCode::class);
        $token = app('auth.password')->broker('customers')->createToken($customer);
        $this->postJson('/api/cstmr/password/reset', [
            'email' => $customer->email, 'token' => $token,
            'password' => 'Updated123', 'password_confirmation' => 'Updated123',
        ])->assertOk();

        $this->get('/login')->assertOk()->assertSee('password');
        $this->get('/register')->assertNotFound();
        $admin = User::factory()->create();
        $this->post('/login', ['email' => $admin->email, 'password' => 'password'])->assertRedirect('/home');
        $this->get('/home')->assertOk()->assertViewIs('home');
        $this->get('/products')->assertOk()->assertViewIs('products.index');
    }
}
