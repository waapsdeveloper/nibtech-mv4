<?php

namespace Tests\Unit;

use App\Http\Controllers\RefurbedAPIController;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class RefurbedAPIControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
    }

    public function test_list_orders_sends_authorized_request(): void
    {
        config(['services.refurbed' => [
            'api_key' => 'test-key',
            'base_url' => 'https://api.refurbed.com',
            'auth_scheme' => 'Bearer',
            'user_agent' => 'PHPUnit/Refurbed',
            'timeout' => 5,
            'max_retries' => 0,
            'retry_delay_ms' => 0,
        ]]);

        Http::fake([
            'https://api.refurbed.com/refb.merchant.v1.OrderService/ListOrders' => Http::response(['orders' => []], 200),
        ]);

        $controller = app(RefurbedAPIController::class);
        $response = $controller->listOrders([
            'state' => ['any_of' => ['NEW']],
        ]);

        $this->assertSame([], $response['orders']);

        Http::assertSent(function (Request $request) {
            return $request->url() === 'https://api.refurbed.com/refb.merchant.v1.OrderService/ListOrders'
                && $request->hasHeader('Authorization', 'Bearer test-key')
                && $request->hasHeader('User-Agent', 'PHPUnit/Refurbed')
                && $request['filter']['state']['any_of'][0] === 'NEW';
        });
    }

    public function test_missing_api_key_throws_exception(): void
    {
        config(['services.refurbed' => ['api_key' => null]]);

        $this->expectException(RuntimeException::class);

        app(RefurbedAPIController::class);
    }
}
