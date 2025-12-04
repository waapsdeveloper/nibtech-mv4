<?php

use App\Http\Controllers\V2\ListingController as V2ListingController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| V2 Routes
|--------------------------------------------------------------------------
|
| Here is where you can register V2 routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. V2 routes are prefixed with /v2.
|
*/

Route::prefix('v2')->group(function () {
    Route::get('listings', [V2ListingController::class, 'index'])->name('v2.view_listing');
    Route::get('listings/get_variations', [V2ListingController::class, 'getVariations'])->name('v2.get_variations');
    Route::post('listings/render_listing_items', [V2ListingController::class, 'renderListingItems'])->name('v2.render_listing_items');
    Route::post('listings/clear_cache', [V2ListingController::class, 'clearCache'])->name('v2.clear_listing_cache');
});

