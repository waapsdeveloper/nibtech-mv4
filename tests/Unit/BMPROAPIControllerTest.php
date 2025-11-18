<?php

namespace Tests\Unit;

use App\Http\Controllers\BMPROAPIController;
use App\Services\MarketplaceTokenResolver;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class BMPROAPIControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        $this->setEnv('BMPRO_API_TOKEN', 'test-token');
        $this->setEnv('BMPRO_API_USER_AGENT', 'PHPUnit/BMPRO');
    }

    public function test_get_order_sends_authenticated_request(): void
    {
        Http::fake([
            'https://api.pro.backmarket.com/sellers-prod/2024-03/orders/12345' => Http::response([
                'id' => 12345,
            ], 200),
        ]);

        $controller = app(BMPROAPIController::class);

        $response = $controller->getOrder('12345');

        $this->assertTrue($response['success']);
        $this->assertSame(['id' => 12345], $response['data']);

        Http::assertSent(function (Request $request) {
            return $request->url() === 'https://api.pro.backmarket.com/sellers-prod/2024-03/orders/12345'
                && $request->hasHeader('Authorization', 'Bearer test-token')
                && $request->hasHeader('User-Agent', 'PHPUnit/BMPRO');
        });
    }

    public function test_missing_token_throws_exception(): void
    {
        $this->setEnv('BMPRO_API_TOKEN', null);

        $controller = app(BMPROAPIController::class);

        $this->expectException(RuntimeException::class);

        Http::fake();

        $controller->getOrder('12345');
    }

    public function test_marketplace_option_uses_database_token_when_available(): void
    {
        $fakeResolver = new class extends MarketplaceTokenResolver {
            public function resolve(?int $marketplaceId = null, ?string $currency = null): ?string
            {
                if ($marketplaceId === 2 || ($currency && strtoupper($currency) === 'EUR')) {
                    return 'db-token';
                }

                return null;
            }
        };

        $this->app->instance(MarketplaceTokenResolver::class, $fakeResolver);

        Http::fake([
            'https://api.pro.backmarket.com/sellers-prod/2024-03/orders/12345' => Http::response([
                'id' => 12345,
            ], 200),
        ]);

        $controller = app(BMPROAPIController::class);

        $controller->getOrder('12345', 'prod', ['marketplace_id' => 2]);

        Http::assertSent(function (Request $request) {
            return $request->hasHeader('Authorization', 'Bearer db-token');
        });
    }

    protected function setEnv(string $key, ?string $value): void
    {
        if ($value === null) {
            putenv($key);
            unset($_ENV[$key], $_SERVER[$key]);

            return;
        }

        putenv($key . '=' . $value);
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
}
