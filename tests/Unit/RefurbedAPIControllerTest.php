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
        $this->configureRefurbedClient();

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

    public function test_batch_update_order_items_chunks_payloads(): void
    {
        $this->configureRefurbedClient();

        Http::fake([
            'https://api.refurbed.com/refb.merchant.v1.OrderItemService/BatchUpdateOrderItems' => Http::response(['result' => 'ok'], 200),
        ]);

        $updates = [];
        for ($i = 0; $i < 55; $i++) {
            $updates[] = ['id' => "item-{$i}", 'state' => 'SHIPPED'];
        }

        $controller = app(RefurbedAPIController::class);
        $result = $controller->batchUpdateOrderItems($updates);

        $this->assertSame(55, $result['total']);
        $this->assertCount(2, $result['batches']);

        $recorded = Http::recorded();
        $this->assertCount(2, $recorded);

        /** @var Request $firstRequest */
        $firstRequest = $recorded[0][0];
        /** @var Request $secondRequest */
        $secondRequest = $recorded[1][0];

        $this->assertCount(50, $firstRequest->data()['order_item_updates']);
        $this->assertCount(5, $secondRequest->data()['order_item_updates']);
    }

    public function test_batch_update_order_item_state_accepts_custom_options(): void
    {
        $this->configureRefurbedClient();

        Http::fake([
            'https://api.refurbed.com/refb.merchant.v1.OrderItemService/BatchUpdateOrderItemsState' => Http::response(['result' => 'ok'], 200),
        ]);

        $updates = [
            ['id' => 'order-item-1', 'state' => 'SHIPPED', 'parcel_tracking_number' => 'TRACK-1'],
            ['id' => 'order-item-2', 'state' => 'DELIVERED', 'parcel_tracking_number' => 'TRACK-2'],
        ];

        $controller = app(RefurbedAPIController::class);
        $result = $controller->batchUpdateOrderItemsState($updates, [
            'chunk_size' => 1,
            'body' => ['dry_run' => true],
        ]);

        $this->assertSame(2, $result['total']);
        $this->assertCount(2, $result['batches']);

        $recorded = Http::recorded();
        $this->assertCount(2, $recorded);

        foreach ($recorded as $entry) {
            /** @var Request $request */
            $request = $entry[0];
            $payload = $request->data();

            $this->assertTrue($payload['dry_run']);
            $this->assertArrayHasKey('order_item_state_updates', $payload);
            $this->assertCount(1, $payload['order_item_state_updates']);
        }
    }

    private function configureRefurbedClient(): void
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
    }
}
