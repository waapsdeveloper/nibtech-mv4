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
    Route::get('listings/get_updated_quantity/{id}', [V2ListingController::class, 'getUpdatedQuantity'])->name('v2.listing.get_updated_quantity');
    
    // Marketplace routes
    Route::get('marketplace', [Marketplace::class, 'render'])->name('v2.view_marketplace');
    Route::get('marketplace/add', [Marketplace::class, 'add_marketplace'])->name('v2.add_marketplace');
    Route::post('marketplace/insert', [Marketplace::class, 'insert_marketplace'])->name('v2.insert_marketplace');
    Route::get('marketplace/edit/{id}', [Marketplace::class, 'edit_marketplace'])->name('v2.edit_marketplace');
    Route::post('marketplace/update/{id}', [Marketplace::class, 'update_marketplace'])->name('v2.update_marketplace');
    Route::get('marketplace/delete/{id}', [Marketplace::class, 'delete_marketplace'])->name('v2.delete_marketplace');
    
    // Marketplace sync routes
    Route::post('marketplace/sync/{id}', [Marketplace::class, 'sync_marketplace'])->name('v2.sync_marketplace');
    Route::post('marketplace/sync-all', [Marketplace::class, 'sync_all_marketplaces'])->name('v2.sync_all_marketplaces');
    Route::get('marketplace/sync-status/{id}', [Marketplace::class, 'get_sync_status'])->name('v2.get_sync_status');
    
    // Stock Formula routes
    Route::get('marketplace/stock-formula', [MarketplaceStockFormulaController::class, 'index'])->name('v2.marketplace.stock_formula');
    Route::get('marketplace/stock-formula/search', [MarketplaceStockFormulaController::class, 'searchVariations'])->name('v2.marketplace.stock_formula.search');
    Route::get('marketplace/stock-formula/{variationId}/stocks', [MarketplaceStockFormulaController::class, 'getMarketplaceStocks'])->name('v2.marketplace.stock_formula.stocks');
    Route::post('marketplace/stock-formula/{variationId}/formula/{marketplaceId}', [MarketplaceStockFormulaController::class, 'saveFormula'])->name('v2.marketplace.stock_formula.save');
    Route::delete('marketplace/stock-formula/{variationId}/formula/{marketplaceId}', [MarketplaceStockFormulaController::class, 'deleteFormula'])->name('v2.marketplace.stock_formula.delete');
    Route::post('marketplace/stock-formula/{variationId}/stock/{marketplaceId}/reset', [MarketplaceStockFormulaController::class, 'resetStock'])->name('v2.marketplace.stock_formula.reset_stock');
    
    // V2 Listing API routes
    Route::post('listings/add_quantity/{id}', [V2ListingController::class, 'add_quantity'])->name('v2.listing.add_quantity');
    Route::get('listings/get_listing_history/{id}', [V2ListingController::class, 'get_listing_history'])->name('v2.listing.get_listing_history');
    Route::post('listings/record_change', [V2ListingController::class, 'record_listing_change'])->name('v2.listing.record_change');
    Route::post('listings/update_price/{id}', [V2ListingController::class, 'update_price'])->name('v2.listing.update_price');
    Route::post('listings/update_limit/{id}', [V2ListingController::class, 'update_limit'])->name('v2.listing.update_limit');
    Route::post('listings/update_marketplace_handlers/{variationId}/{marketplaceId}', [V2ListingController::class, 'update_marketplace_handlers'])->name('v2.listing.update_marketplace_handlers');
    Route::post('listings/update_marketplace_prices/{variationId}/{marketplaceId}', [V2ListingController::class, 'update_marketplace_prices'])->name('v2.listing.update_marketplace_prices');
    
    // Stock Locks Dashboard
    Route::get('stock-locks', [\App\Http\Livewire\V2\StockLocks::class, 'index'])->name('v2.stock-locks');

    // Stock Locks API (returns HTML Blade template)
    Route::get('stock-locks/api', [\App\Http\Controllers\V2\StockLocksController::class, 'getLocks'])->name('v2.stock-locks.api');
    
    // Stock Locks API JSON (for backward compatibility)
    Route::get('stock-locks/api/json', [\App\Http\Controllers\V2\StockLocksController::class, 'getLocksJson'])->name('v2.stock-locks.api.json');
    
    // Stock Locks Actions
    Route::post('stock-locks/{lockId}/release', [\App\Http\Controllers\V2\StockLocksController::class, 'releaseLock'])->name('v2.stock-locks.release');
    
    // Artisan Commands Guide
    Route::get('artisan-commands', [\App\Http\Controllers\V2\ArtisanCommandsController::class, 'index'])->name('v2.artisan-commands');
    Route::post('artisan-commands/execute', [\App\Http\Controllers\V2\ArtisanCommandsController::class, 'execute'])->name('v2.artisan-commands.execute');
    Route::post('artisan-commands/run-migrations', [\App\Http\Controllers\V2\ArtisanCommandsController::class, 'runMigrations'])->name('v2.artisan-commands.run-migrations');
    Route::get('artisan-commands/migration-details', [\App\Http\Controllers\V2\ArtisanCommandsController::class, 'getMigrationDetails'])->name('v2.artisan-commands.migration-details');
    Route::post('artisan-commands/record-migration', [\App\Http\Controllers\V2\ArtisanCommandsController::class, 'recordMigration'])->name('v2.artisan-commands.record-migration');
    Route::post('artisan-commands/run-single-migration', [\App\Http\Controllers\V2\ArtisanCommandsController::class, 'runSingleMigration'])->name('v2.artisan-commands.run-single-migration');
    Route::get('artisan-commands/check-command-status', [\App\Http\Controllers\V2\ArtisanCommandsController::class, 'checkCommandStatus'])->name('v2.artisan-commands.check-command-status');
    Route::get('artisan-commands/documentation', [\App\Http\Controllers\V2\ArtisanCommandsController::class, 'getDocumentation'])->name('v2.artisan-commands.documentation');
    
    // Stock Sync Logs
    Route::get('logs/stock-sync', [\App\Http\Controllers\V2\StockSyncLogController::class, 'index'])->name('v2.logs.stock-sync');
    Route::get('logs/stock-sync/{id}', [\App\Http\Controllers\V2\StockSyncLogController::class, 'show'])->name('v2.logs.stock-sync.show');
    Route::delete('logs/stock-sync/{id}', [\App\Http\Controllers\V2\StockSyncLogController::class, 'destroy'])->name('v2.logs.stock-sync.destroy');
    Route::patch('logs/stock-sync/{id}/status', [\App\Http\Controllers\V2\StockSyncLogController::class, 'updateStatus'])->name('v2.logs.stock-sync.update-status');
});

