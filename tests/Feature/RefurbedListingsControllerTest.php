<?php

namespace Tests\Feature;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RefurbedListingsControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();

        config(['services.refurbed' => [
            'api_key' => 'test-key',
            'base_url' => 'https://api.refurbed.com',
            'auth_scheme' => 'Bearer',
            'user_agent' => 'PHPUnit/Refurbed',
            'timeout' => 5,
            'max_retries' => 0,
            'retry_delay_ms' => 0,
        ]]);

        config([
            'logging.default' => 'single',
            'logging.channels.single' => [
                'driver' => 'single',
                'path' => storage_path('logs/laravel.log'),
                'level' => 'debug',
            ],
        ]);
    }

    public function test_active_listings_endpoint_returns_active_state_by_default(): void
    {
        Http::fake([
            'https://api.refurbed.com/refb.merchant.v1.OfferService/ListOffers' => Http::response([
                'offers' => [
                    ['id' => 'offer-1'],
                ],
            ], 200),
        ]);

        $response = $this->withoutExceptionHandling()
            ->getJson('/api/refurbed/listings/active?per_page=25');

        $response->assertOk()
            ->assertJsonFragment(['id' => 'offer-1']);

        Http::assertSent(function (Request $request) {
            return $request->url() === 'https://api.refurbed.com/refb.merchant.v1.OfferService/ListOffers'
                && ($request['filter']['state']['any_of'] ?? []) === ['ACTIVE']
                && ($request['pagination']['page_size'] ?? null) === 25;
        });
    }

    public function test_ship_order_lines_updates_only_accepted_items(): void
    {
        Http::fake([
            'https://api.refurbed.com/refb.merchant.v1.OrderItemService/ListOrderItemsByOrder' => Http::response([
                'order_items' => [
                    ['id' => 'line-1', 'state' => 'ACCEPTED'],
                    ['id' => 'line-2', 'state' => 'SHIPPED'],
                ],
                'has_more' => false,
            ], 200),
            'https://api.refurbed.com/refb.merchant.v1.OrderItemService/UpdateOrderItemState' => Http::response([
                'result' => 'ok',
            ], 200),
        ]);

        $response = $this->postJson('/api/refurbed/orders/REF-123/ship-lines', [
            'tracking_number' => 'TRACK123',
            'carrier' => 'DHL',
        ]);

        $response->assertOk()
            ->assertJson([
                'order_id' => 'REF-123',
                'updated' => 1,
                'skipped' => ['line-2'],
            ]);

        Http::assertSent(function (Request $request) {
            return $request->url() === 'https://api.refurbed.com/refb.merchant.v1.OrderItemService/ListOrderItemsByOrder'
                && $request['order_id'] === 'REF-123';
        });

        Http::assertSent(function (Request $request) {
            if ($request->url() !== 'https://api.refurbed.com/refb.merchant.v1.OrderItemService/UpdateOrderItemState') {
                return false;
            }

            return $request['id'] === 'line-1'
                && $request['state'] === 'SHIPPED'
                && $request['parcel_tracking_number'] === 'TRACK123'
                && $request['parcel_tracking_carrier'] === 'DHL';
        });
    }

    public function test_ship_order_lines_returns_noop_when_nothing_to_update(): void
    {
        Http::fake([
            'https://api.refurbed.com/refb.merchant.v1.OrderItemService/ListOrderItemsByOrder' => Http::response([
                'order_items' => [
                    ['id' => 'line-1', 'state' => 'SHIPPED'],
                ],
            ], 200),
        ]);

        $response = $this->postJson('/api/refurbed/orders/REF-999/ship-lines');

        $response->assertStatus(422)
            ->assertJson([
                'status' => 'noop',
                'order_id' => 'REF-999',
            ]);

        Http::assertSentCount(1);
    }

    public function test_ship_order_lines_accepts_identifier_option(): void
    {
        Http::fake([
            'https://api.refurbed.com/refb.merchant.v1.OrderItemService/ListOrderItemsByOrder' => Http::response([
                'order_items' => [
                    ['id' => 'line-1', 'state' => 'ACCEPTED'],
                ],
            ], 200),
            'https://api.refurbed.com/refb.merchant.v1.OrderItemService/UpdateOrderItemState' => Http::response([
                'result' => 'ok',
            ], 200),
        ]);

        $response = $this->postJson('/api/refurbed/orders/REF-444/ship-lines', [
            'order_item_ids' => ['line-1'],
            'imei' => '359876543210123',
        ]);

        $response->assertOk()
            ->assertJson([
                'order_id' => 'REF-444',
                'updated' => 1,
            ]);

        Http::assertSent(function (Request $request) {
            if ($request->url() !== 'https://api.refurbed.com/refb.merchant.v1.OrderItemService/UpdateOrderItemState') {
                return false;
            }

            return $request['identifier'] === '359876543210123'
                && $request['identifier_label'] === 'IMEI';
        });
    }
}
