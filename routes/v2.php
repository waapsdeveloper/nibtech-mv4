<?php

use App\Http\Controllers\V2\ListingController as V2ListingController;
use App\Http\Controllers\V2\MarketplaceStockFormulaController;
use App\Http\Livewire\V2\Marketplace;
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
    Route::get('listings/get_variation_history/{id}', [V2ListingController::class, 'get_variation_history'])->name('v2.get_variation_history');
    Route::get('listings/get_listings/{variationId}', [V2ListingController::class, 'get_listings'])->name('v2.get_listings');
    
    // Marketplace routes
    Route::get('marketplace', [Marketplace::class, 'render'])->name('v2.view_marketplace');
    Route::get('marketplace/add', [Marketplace::class, 'add_marketplace'])->name('v2.add_marketplace');
    Route::post('marketplace/insert', [Marketplace::class, 'insert_marketplace'])->name('v2.insert_marketplace');
    Route::get('marketplace/edit/{id}', [Marketplace::class, 'edit_marketplace'])->name('v2.edit_marketplace');
    Route::post('marketplace/update/{id}', [Marketplace::class, 'update_marketplace'])->name('v2.update_marketplace');
    Route::get('marketplace/delete/{id}', [Marketplace::class, 'delete_marketplace'])->name('v2.delete_marketplace');
    
    // Stock Formula routes
    Route::get('marketplace/stock-formula', [MarketplaceStockFormulaController::class, 'index'])->name('v2.marketplace.stock_formula');
    Route::get('marketplace/stock-formula/search', [MarketplaceStockFormulaController::class, 'searchVariations'])->name('v2.marketplace.stock_formula.search');
    Route::get('marketplace/stock-formula/{variationId}/stocks', [MarketplaceStockFormulaController::class, 'getMarketplaceStocks'])->name('v2.marketplace.stock_formula.stocks');
    Route::post('marketplace/stock-formula/{variationId}/formula/{marketplaceId}', [MarketplaceStockFormulaController::class, 'saveFormula'])->name('v2.marketplace.stock_formula.save');
    Route::delete('marketplace/stock-formula/{variationId}/formula/{marketplaceId}', [MarketplaceStockFormulaController::class, 'deleteFormula'])->name('v2.marketplace.stock_formula.delete');
});

