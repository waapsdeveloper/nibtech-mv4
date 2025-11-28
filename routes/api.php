<?php

use App\Http\Controllers\ApiRequestController;
use App\Http\Controllers\BMPROListingsController;
use App\Http\Controllers\BMPROOrdersController;
use App\Http\Controllers\InternalApiController;
use App\Http\Controllers\RefurbedListingsController;
use App\Http\Controllers\RefurbedWebhookController;
use App\Http\Controllers\TestingController;
use App\Models\Admin_model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

// Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
//     return $request->user();
// });

// Route::get('/token/create', function (Request $request) {
//     $token = Admin_model::find(1)->createToken('dr_phone');

//     return ['token' => $token->plainTextToken];
// });

Route::get('/test', function (Request $request) {
    return response()->json('Hello');
});

// Refurbed webhook endpoint (public, no auth required for external webhooks)
Route::post('/refurbed/webhook', [RefurbedWebhookController::class, 'handleWebhook'])
    ->name('refurbed.webhook');

Route::group(['middleware' => ['auth:sanctum']], function () {
    // return response()->json('Hello');
    Route::resource('/request', ApiRequestController::class);
    // Route::post('request', [ApiRequestController::class, 'store']);
});

Route::group(['middleware' => ['internal.only']], function () {
    // Route::get('/internal/get_variations', [InternalApiController::class, 'get_variations']);
    // Route::get('/internal/get_sales/{id}', [InternalApiController::class, 'get_sales']);
    // Route::get('/internal/get_variation_available_stocks/{id}', [InternalApiController::class, 'get_variation_available_stocks']);
    // Route::get('/internal/get_updated_quantity/{id}', [InternalApiController::class, 'getUpdatedQuantity']);
    // Route::get('/internal/get_competitors/{id}/{no_check?}', [InternalApiController::class, 'getCompetitors']);
    // Route::get('/internal/inventory_get_vendor_wise_average', [InternalApiController::class, 'inventoryGetVendorWiseAverage']);
    // Route::get('/internal/inventory_get_average_cost', [InternalApiController::class, 'inventoryGetAverageCost']);

    Route::prefix('refurbed')->group(function () {
        Route::get('/listings/test', [RefurbedListingsController::class, 'test'])
            ->name('refurbed.listings.test');
        Route::get('/listings/active', [RefurbedListingsController::class, 'active'])
            ->name('refurbed.listings.active');
        Route::get('/orders', [RefurbedListingsController::class, 'orders'])
            ->name('refurbed.orders.index');
        Route::post('/orders/{order}/shipping-label', [RefurbedListingsController::class, 'createOrderShippingLabel'])
            ->name('refurbed.orders.shipping_label');
        Route::post('/orders/{order}/ship-lines', [RefurbedListingsController::class, 'shipOrderLines'])
            ->name('refurbed.orders.ship_lines');
        Route::get('/listings/zero-stock', [RefurbedListingsController::class, 'zeroStock'])
            ->name('refurbed.listings.zero_stock');
        Route::get('/listings/sync', [RefurbedListingsController::class, 'syncListings'])
            ->name('refurbed.listings.sync');
        Route::get('/listings/update-stock', [RefurbedListingsController::class, 'updateStockFromSystem'])
            ->name('refurbed.listings.update_stock');
        Route::get('/listings/update-bm-prices', [RefurbedListingsController::class, 'updatePricesFromBackMarket'])
            ->name('refurbed.listings.update_bm_prices');
    });

    Route::get('/bmpro/listings/test', [BMPROListingsController::class, 'index'])
        ->name('bmpro.listings.test');

    Route::get('/bmpro/orders/pending', [BMPROOrdersController::class, 'pending'])
        ->name('bmpro.orders.pending');



});
