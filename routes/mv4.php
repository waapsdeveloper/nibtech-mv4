<?php

use App\Http\Controllers\ProductSyncController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Syntora MV4 API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for Syntora Marketplace V4.
| These routes are loaded by the RouteServiceProvider directly without
| the "api" prefix. All MV4 sync routes are prefixed with /mv4/sync.
| Final URL format: domain/mv4/sync/products
|
*/

// Product Sync API routes for Syntora MV4
Route::prefix('mv4/sync')->middleware(['validate.sync.api'])->group(function () {
    Route::get('/products', [ProductSyncController::class, 'syncProducts'])
        ->name('mv4.sync.products');
    Route::get('/products/updated', [ProductSyncController::class, 'syncUpdatedProducts'])
        ->name('mv4.sync.products.updated');
});
