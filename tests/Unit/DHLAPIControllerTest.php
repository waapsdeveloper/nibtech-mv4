<?php

namespace Tests\Unit;

use App\Http\Controllers\DHLAPIController;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class DHLAPIControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        config(['cache.default' => 'array']);
        Cache::store('array')->clear();
    }

    public function test_create_shipment_uses_cached_token_and_account_number(): void
    {
        config(['services.dhl' => [
            'base_url' => 'https://api.dhl.test',
            'auth_url' => 'https://auth.dhl.test/oauth/token',
            'client_id' => 'client-id',
            'client_secret' => 'client-secret',
            'account_number' => '123456789',
            'timeout' => 5,
            'max_retries' => 0,
            'retry_delay_ms' => 0,
            'token_ttl' => 3600,
            'cache_store' => 'array',
        ]]);

        Http::fake([
            'https://auth.dhl.test/oauth/token' => Http::response([
                'access_token' => 'token-abc',
                'expires_in' => 3600,
            ], 200),
            'https://api.dhl.test/shipments' => Http::response([
                'shipmentTrackingNumber' => 'JD123456789',
            ], 200),
        ]);

        $controller = app(DHLAPIController::class);

        $response = $controller->createShipment([
            'plannedShippingDateAndTime' => '2024-01-01T12:00:00GMT+01:00',
        ]);

        $this->assertSame('JD123456789', $response['shipmentTrackingNumber']);

        Http::assertSent(function (Request $request) {
            return $request->url() === 'https://auth.dhl.test/oauth/token'
                && $request['grant_type'] === 'client_credentials';
        });

        Http::assertSent(function (Request $request) {
            if ($request->url() !== 'https://api.dhl.test/shipments') {
                return false;
            }

            return $request->hasHeader('Authorization', 'Bearer token-abc')
                && $request->data()['accounts'][0]['number'] === '123456789';
        });
    }

    public function test_create_shipping_label_posts_payload(): void
    {
        config(['services.dhl' => [
            'base_url' => 'https://api.dhl.test',
            'auth_url' => 'https://auth.dhl.test/oauth/token',
            'client_id' => 'client-id',
            'client_secret' => 'client-secret',
            'timeout' => 5,
            'max_retries' => 0,
            'retry_delay_ms' => 0,
            'token_ttl' => 3600,
            'cache_store' => 'array',
        ]]);

        Http::fake([
            'https://auth.dhl.test/oauth/token' => Http::response([
                'access_token' => 'token-abc',
                'expires_in' => 3600,
            ], 200),
            'https://api.dhl.test/shipments/labels' => Http::response([
                'labelId' => 'LBL123',
            ], 200),
        ]);

        $controller = app(DHLAPIController::class);

        $response = $controller->createShippingLabel([
            'shipmentTrackingNumber' => 'JD123456789',
            'labelFormat' => 'PDF',
        ]);

        $this->assertSame('LBL123', $response['labelId']);

        Http::assertSent(function (Request $request) {
            return $request->url() === 'https://api.dhl.test/shipments/labels'
                && $request->hasHeader('Authorization', 'Bearer token-abc')
                && $request['shipmentTrackingNumber'] === 'JD123456789';
        });
    }

    public function test_missing_credentials_throw_exception(): void
    {
        config(['services.dhl' => [
            'client_id' => null,
            'client_secret' => null,
        ]]);

        $this->expectException(RuntimeException::class);

        app(DHLAPIController::class);
    }
}
