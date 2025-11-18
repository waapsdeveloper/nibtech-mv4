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
}
