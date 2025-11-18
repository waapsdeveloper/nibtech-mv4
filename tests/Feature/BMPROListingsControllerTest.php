<?php

namespace Tests\Feature;

use App\Services\MarketplaceTokenResolver;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BMPROListingsControllerTest extends TestCase
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

    public function test_test_endpoint_fetches_listings_using_marketplace_token(): void
    {
        Http::fakeSequence()
            ->push([
                'items' => [
                    ['id' => 'listing-1'],
                ],
                'links' => ['next' => 'next-page'],
            ], 200)
            ->push([
                'items' => [
                    ['id' => 'listing-2'],
                ],
                'links' => [],
            ], 200);

        $response = $this->withoutExceptionHandling()
            ->getJson('/api/bmpro/listings/test?per_page=25&currency=EUR');

        $response->assertOk()
            ->assertJsonFragment(['id' => 'listing-1'])
            ->assertJsonFragment(['id' => 'listing-2']);

        Http::assertSentCount(2);

        Http::assertSent(function (Request $request) {
            return str_starts_with($request->url(), 'https://api.pro.backmarket.com/sellers-prod/2024-03/listings')
                && str_contains($request->url(), 'page-size=25')
                && $request->hasHeader('Authorization', 'Bearer db-token');
        });
    }
}
