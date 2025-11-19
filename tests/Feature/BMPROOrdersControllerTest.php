<?php

namespace Tests\Feature;

use App\Services\MarketplaceTokenResolver;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BMPROOrdersControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();

        $resolver = new class extends MarketplaceTokenResolver {
            public function resolve(?int $marketplaceId = null, ?string $currency = null): ?string
            {
                if ($marketplaceId === 2 || ($currency && strtoupper($currency) === 'EUR')) {
                    return 'db-token';
                }

                return null;
            }
        };

        $this->app->instance(MarketplaceTokenResolver::class, $resolver);
    }

    public function test_pending_endpoint_fetches_orders_with_marketplace_token(): void
    {
        Http::fakeSequence()
            ->push([
                'items' => [
                    ['id' => 'order-1'],
                ],
                'links' => ['next' => 'next-page'],
            ], 200)
            ->push([
                'items' => [
                    ['id' => 'order-2'],
                ],
                'links' => [],
            ], 200);

        $response = $this->withoutExceptionHandling()
            ->getJson('/api/bmpro/orders/pending?per_page=25&currency=EUR');

        $response->assertOk()
            ->assertJsonFragment(['id' => 'order-1'])
            ->assertJsonFragment(['id' => 'order-2']);

        Http::assertSentCount(2);

        Http::assertSent(function (Request $request) {
            return str_starts_with($request->url(), 'https://api.pro.backmarket.com/sellers-prod/2024-03/orders')
                && str_contains($request->url(), 'page-size=25')
                && str_contains($request->url(), 'fulfillment_status=fulfilled')
                && $request->hasHeader('Authorization', 'Bearer db-token');
        });
    }

    public function test_pending_endpoint_defaults_to_pending_status_when_not_set(): void
    {
        Http::fake([
            '*' => Http::response([
                'items' => [],
                'links' => [],
            ], 200),
        ]);

        $response = $this->withoutExceptionHandling()
            ->getJson('/api/bmpro/orders/pending?currency=EUR&fulfillment_status=');

        $response->assertOk();

        Http::assertSent(function (Request $request) {
            return str_contains($request->url(), 'fulfillment_status=pending');
        });
    }
}
