<?php

use App\Http\Controllers\V2\ListingController as V2ListingController;
use App\Http\Controllers\V2\MarketplaceStockFormulaController;
use App\Http\Controllers\V2\TeamController;
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
    Route::get('listings/get_competitors/{variationId}/{no_check?}', [V2ListingController::class, 'getCompetitors'])->name('v2.get_competitors');
    Route::get('listings/get_updated_quantity/{id}', [V2ListingController::class, 'getUpdatedQuantity'])->name('v2.listing.get_updated_quantity');
    Route::get('listings/get_marketplace_stock_comparison/{id}', [V2ListingController::class, 'getMarketplaceStockComparison'])->name('v2.listing.get_marketplace_stock_comparison');
    Route::post('listings/fix_stock_mismatch/{id}', [V2ListingController::class, 'fixStockMismatch'])->name('v2.listing.fix_stock_mismatch');
    
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
    Route::get('marketplace/stock-formula/{variationId}/modal', [MarketplaceStockFormulaController::class, 'getModalContent'])->name('v2.marketplace.stock_formula.modal');
    Route::get('marketplace/stock-formula/{variationId}/stocks', [MarketplaceStockFormulaController::class, 'getMarketplaceStocks'])->name('v2.marketplace.stock_formula.stocks');
    Route::post('marketplace/stock-formula/{variationId}/formula/{marketplaceId}', [MarketplaceStockFormulaController::class, 'saveFormula'])->name('v2.marketplace.stock_formula.save');
    Route::delete('marketplace/stock-formula/{variationId}/formula/{marketplaceId}', [MarketplaceStockFormulaController::class, 'deleteFormula'])->name('v2.marketplace.stock_formula.delete');
    Route::delete('marketplace/stock-formula/{variationId}/delete-all-formulas', [MarketplaceStockFormulaController::class, 'deleteAllFormulas'])->name('v2.marketplace.stock_formula.delete_all');
    Route::post('marketplace/stock-formula/{variationId}/stock/{marketplaceId}/reset', [MarketplaceStockFormulaController::class, 'resetStock'])->name('v2.marketplace.stock_formula.reset_stock');
    
    // Default Formula routes
    Route::get('marketplace/stock-formula/global-defaults', [MarketplaceStockFormulaController::class, 'globalDefaults'])->name('v2.marketplace.stock_formula.global_defaults');
    Route::post('marketplace/stock-formula/global-default/{marketplaceId}', [MarketplaceStockFormulaController::class, 'saveGlobalDefault'])->name('v2.marketplace.stock_formula.save_global_default');
    Route::delete('marketplace/stock-formula/global-default/{marketplaceId}', [MarketplaceStockFormulaController::class, 'deleteGlobalDefault'])->name('v2.marketplace.stock_formula.delete_global_default');
    Route::post('marketplace/stock-formula/{variationId}/variation-default', [MarketplaceStockFormulaController::class, 'saveVariationDefault'])->name('v2.marketplace.stock_formula.save_variation_default');
    Route::get('marketplace/stock-formula/global-defaults/api', [MarketplaceStockFormulaController::class, 'getGlobalDefaults'])->name('v2.marketplace.stock_formula.get_global_defaults');
    
    // V2 Listing API routes
    Route::post('listings/add_quantity/{id}', [V2ListingController::class, 'add_quantity'])->name('v2.listing.add_quantity');
    Route::post('listings/set_listed_available/{id}', [V2ListingController::class, 'set_listed_available'])->name('v2.listing.set_listed_available');
    Route::get('listings/get_listing_history/{id}', [V2ListingController::class, 'get_listing_history'])->name('v2.listing.get_listing_history');
    Route::post('listings/record_change', [V2ListingController::class, 'record_listing_change'])->name('v2.listing.record_change');
    Route::post('listings/update_price/{id}', [V2ListingController::class, 'update_price'])->name('v2.listing.update_price');
    Route::post('listings/update_limit/{id}', [V2ListingController::class, 'update_limit'])->name('v2.listing.update_limit');
    Route::post('listings/update_marketplace_handlers/{variationId}/{marketplaceId}', [V2ListingController::class, 'update_marketplace_handlers'])->name('v2.listing.update_marketplace_handlers');
    Route::post('listings/update_marketplace_prices/{variationId}/{marketplaceId}', [V2ListingController::class, 'update_marketplace_prices'])->name('v2.listing.update_marketplace_prices');
    Route::post('listings/restore_history/{id}', [V2ListingController::class, 'restore_history'])->name('v2.listing.restore_history');
    
    // Stock Locks Dashboard - REMOVED (Stock lock system removed)
    // Route::get('stock-locks', [\App\Http\Livewire\V2\StockLocks::class, 'index'])->name('v2.stock-locks');
    // Route::get('stock-locks/api', [\App\Http\Controllers\V2\StockLocksController::class, 'getLocks'])->name('v2.stock-locks.api');
    // Route::get('stock-locks/api/json', [\App\Http\Controllers\V2\StockLocksController::class, 'getLocksJson'])->name('v2.stock-locks.api.json');
    // Route::post('stock-locks/{lockId}/release', [\App\Http\Controllers\V2\StockLocksController::class, 'releaseLock'])->name('v2.stock-locks.release');
    
    // Artisan Commands Guide
    Route::get('artisan-commands', [\App\Http\Controllers\V2\ArtisanCommandsController::class, 'index'])->name('v2.artisan-commands');
    Route::post('artisan-commands/execute', [\App\Http\Controllers\V2\ArtisanCommandsController::class, 'execute'])->name('v2.artisan-commands.execute');
    Route::post('artisan-commands/run-migrations', [\App\Http\Controllers\V2\ArtisanCommandsController::class, 'runMigrations'])->name('v2.artisan-commands.run-migrations');
    Route::get('artisan-commands/migration-details', [\App\Http\Controllers\V2\ArtisanCommandsController::class, 'getMigrationDetails'])->name('v2.artisan-commands.migration-details');
    Route::post('artisan-commands/record-migration', [\App\Http\Controllers\V2\ArtisanCommandsController::class, 'recordMigration'])->name('v2.artisan-commands.record-migration');
    Route::post('artisan-commands/run-single-migration', [\App\Http\Controllers\V2\ArtisanCommandsController::class, 'runSingleMigration'])->name('v2.artisan-commands.run-single-migration');
    Route::get('artisan-commands/check-command-status', [\App\Http\Controllers\V2\ArtisanCommandsController::class, 'checkCommandStatus'])->name('v2.artisan-commands.check-command-status');
    Route::post('artisan-commands/kill', [\App\Http\Controllers\V2\ArtisanCommandsController::class, 'killCommand'])->name('v2.artisan-commands.kill');
    Route::post('artisan-commands/restart', [\App\Http\Controllers\V2\ArtisanCommandsController::class, 'restartCommand'])->name('v2.artisan-commands.restart');
    Route::get('artisan-commands/documentation', [\App\Http\Controllers\V2\ArtisanCommandsController::class, 'getDocumentation'])->name('v2.artisan-commands.documentation');
    Route::get('artisan-commands/pm2-logs', [\App\Http\Controllers\V2\ArtisanCommandsController::class, 'getPm2Logs'])->name('v2.artisan-commands.pm2-logs');
    
    // Stock Sync Logs
    Route::get('logs/stock-sync', [\App\Http\Controllers\V2\StockSyncLogController::class, 'index'])->name('v2.logs.stock-sync');
    Route::get('logs/stock-sync/{id}', [\App\Http\Controllers\V2\StockSyncLogController::class, 'show'])->name('v2.logs.stock-sync.show');
    Route::delete('logs/stock-sync/{id}', [\App\Http\Controllers\V2\StockSyncLogController::class, 'destroy'])->name('v2.logs.stock-sync.destroy');
    Route::patch('logs/stock-sync/{id}/status', [\App\Http\Controllers\V2\StockSyncLogController::class, 'updateStatus'])->name('v2.logs.stock-sync.update-status');
    
    // Log File Viewer
    Route::get('logs/log-file', [\App\Http\Controllers\V2\LogFileController::class, 'index'])->name('v2.logs.log-file');
    Route::delete('logs/log-file', [\App\Http\Controllers\V2\LogFileController::class, 'clear'])->name('v2.logs.log-file.clear');
    Route::get('logs/log-file/download-all', [\App\Http\Controllers\V2\LogFileController::class, 'downloadAllLogs'])->name('v2.logs.log-file.download-all');
    
    // Jobs (Queue Jobs)
    Route::get('logs/jobs', [\App\Http\Controllers\V2\JobsController::class, 'index'])->name('v2.logs.jobs');
    Route::get('logs/jobs/{id}', [\App\Http\Controllers\V2\JobsController::class, 'show'])->name('v2.logs.jobs.show');
    Route::post('logs/jobs/{id}/process', [\App\Http\Controllers\V2\JobsController::class, 'process'])->name('v2.logs.jobs.process');
    Route::post('logs/jobs/process-all', [\App\Http\Controllers\V2\JobsController::class, 'processAll'])->name('v2.logs.jobs.process-all');
    Route::delete('logs/jobs/{id}', [\App\Http\Controllers\V2\JobsController::class, 'destroy'])->name('v2.logs.jobs.destroy');
    Route::delete('logs/jobs', [\App\Http\Controllers\V2\JobsController::class, 'clear'])->name('v2.logs.jobs.clear');
    
    // Failed Jobs
    Route::get('logs/failed-jobs', [\App\Http\Controllers\V2\FailedJobsController::class, 'index'])->name('v2.logs.failed-jobs');
    Route::get('logs/failed-jobs/{id}', [\App\Http\Controllers\V2\FailedJobsController::class, 'show'])->name('v2.logs.failed-jobs.show');
    Route::post('logs/failed-jobs/{id}/retry', [\App\Http\Controllers\V2\FailedJobsController::class, 'retry'])->name('v2.logs.failed-jobs.retry');
    Route::delete('logs/failed-jobs/{id}', [\App\Http\Controllers\V2\FailedJobsController::class, 'destroy'])->name('v2.logs.failed-jobs.destroy');
    Route::delete('logs/failed-jobs', [\App\Http\Controllers\V2\FailedJobsController::class, 'clear'])->name('v2.logs.failed-jobs.clear');
    
    // Log Settings CRUD
    Route::post('logs/log-settings', [\App\Http\Controllers\V2\LogFileController::class, 'storeLogSetting'])->name('v2.logs.log-settings.store');
    Route::put('logs/log-settings/{id}', [\App\Http\Controllers\V2\LogFileController::class, 'updateLogSetting'])->name('v2.logs.log-settings.update');
    Route::delete('logs/log-settings/{id}', [\App\Http\Controllers\V2\LogFileController::class, 'deleteLogSetting'])->name('v2.logs.log-settings.delete');
    Route::get('logs/log-settings/{id}', [\App\Http\Controllers\V2\LogFileController::class, 'getLogSetting'])->name('v2.logs.log-settings.show');
    Route::post('logs/log-settings/{id}/duplicate', [\App\Http\Controllers\V2\LogFileController::class, 'duplicateLogSetting'])->name('v2.logs.log-settings.duplicate');
    
    // Team Management (Options > Teams)
    Route::prefix('options/teams')->group(function () {
        Route::get('/', [TeamController::class, 'index'])->name('v2.options.teams.index');
        Route::get('add-member', [TeamController::class, 'add_member'])->name('v2.options.teams.add-member');
        Route::post('insert-member', [TeamController::class, 'insert_member'])->name('v2.options.teams.insert-member');
        Route::get('edit-member/{id}', [TeamController::class, 'edit_member'])->name('v2.options.teams.edit-member');
        Route::post('update-member/{id}', [TeamController::class, 'update_member'])->name('v2.options.teams.update-member');
        Route::get('update-status/{id}', [TeamController::class, 'update_status'])->name('v2.options.teams.update-status');
        Route::get('get-permissions/{roleId}', [TeamController::class, 'get_permissions'])->name('v2.options.teams.get-permissions');
        Route::post('toggle-role-permission/{roleId}/{permissionId}/{isChecked}', [TeamController::class, 'toggle_role_permission'])->name('v2.options.teams.toggle-role-permission');
        Route::get('get-user-permissions/{userId}', [TeamController::class, 'get_user_permissions'])->name('v2.options.teams.get-user-permissions');
        Route::post('toggle-user-permission/{userId}/{permissionId}/{isChecked}', [TeamController::class, 'toggle_user_permission'])->name('v2.options.teams.toggle-user-permission');
        Route::post('toggle-allow-unknown-ip/{userId}', [TeamController::class, 'toggle_allow_unknown_ip'])->name('v2.options.teams.toggle-allow-unknown-ip');
        Route::get('check-allow-unknown-ip/{userId}', [TeamController::class, 'check_allow_unknown_ip'])->name('v2.options.teams.check-allow-unknown-ip');
    });
    
    // Listing-30 (Extras) – BM sync records: listing_thirty_orders + listing_thirty_order_refs
    Route::prefix('listing-thirty')->name('v2.listing-thirty.')->group(function () {
        Route::get('/', [\App\Http\Controllers\V2\ListingThirtyController::class, 'index'])->name('index');
        Route::get('create', [\App\Http\Controllers\V2\ListingThirtyController::class, 'create'])->name('create');
        Route::post('store', [\App\Http\Controllers\V2\ListingThirtyController::class, 'store'])->name('store');
        Route::post('{id}/refs', [\App\Http\Controllers\V2\ListingThirtyController::class, 'storeRef'])->name('store-ref');
        Route::delete('{id}/refs/{refId}', [\App\Http\Controllers\V2\ListingThirtyController::class, 'destroyRef'])->name('destroy-ref');
        Route::get('{id}/edit', [\App\Http\Controllers\V2\ListingThirtyController::class, 'edit'])->name('edit');
        Route::match(['put', 'patch'], '{id}', [\App\Http\Controllers\V2\ListingThirtyController::class, 'update'])->name('update');
        Route::delete('{id}', [\App\Http\Controllers\V2\ListingThirtyController::class, 'destroy'])->name('destroy');
        Route::get('{id}', [\App\Http\Controllers\V2\ListingThirtyController::class, 'show'])->name('show');
    });

    // Stock Deduction Logs (Extras)
    Route::prefix('stock-deduction-logs')->group(function () {
        Route::get('/', [\App\Http\Controllers\V2\StockDeductionLogController::class, 'index'])->name('v2.stock-deduction-logs.index');
        Route::get('create', [\App\Http\Controllers\V2\StockDeductionLogController::class, 'create'])->name('v2.stock-deduction-logs.create');
        Route::post('store', [\App\Http\Controllers\V2\StockDeductionLogController::class, 'store'])->name('v2.stock-deduction-logs.store');
        Route::post('truncate', [\App\Http\Controllers\V2\StockDeductionLogController::class, 'truncate'])->name('v2.stock-deduction-logs.truncate');
        Route::get('{id}', [\App\Http\Controllers\V2\StockDeductionLogController::class, 'show'])->name('v2.stock-deduction-logs.show');
        Route::get('{id}/edit', [\App\Http\Controllers\V2\StockDeductionLogController::class, 'edit'])->name('v2.stock-deduction-logs.edit');
        Route::match(['put', 'patch'], '{id}', [\App\Http\Controllers\V2\StockDeductionLogController::class, 'update'])->name('v2.stock-deduction-logs.update');
        Route::delete('{id}', [\App\Http\Controllers\V2\StockDeductionLogController::class, 'destroy'])->name('v2.stock-deduction-logs.destroy');
    });
    
    // Listing Stock Comparisons (Extras)
    Route::prefix('listing-stock-comparisons')->group(function () {
        Route::get('/', [\App\Http\Controllers\V2\ListingStockComparisonController::class, 'index'])->name('v2.listing-stock-comparisons.index');
        Route::post('truncate', [\App\Http\Controllers\V2\ListingStockComparisonController::class, 'truncate'])->name('v2.listing-stock-comparisons.truncate');
        Route::get('{id}', [\App\Http\Controllers\V2\ListingStockComparisonController::class, 'show'])->name('v2.listing-stock-comparisons.show');
        Route::delete('{id}', [\App\Http\Controllers\V2\ListingStockComparisonController::class, 'destroy'])->name('v2.listing-stock-comparisons.destroy');
    });
    
    // Marketplace Sync Failures (Extras)
    Route::prefix('marketplace-sync-failures')->group(function () {
        Route::get('/', [\App\Http\Controllers\V2\MarketplaceSyncFailureController::class, 'index'])->name('v2.marketplace-sync-failures.index');
        Route::post('truncate', [\App\Http\Controllers\V2\MarketplaceSyncFailureController::class, 'truncate'])->name('v2.marketplace-sync-failures.truncate');
        Route::delete('{id}', [\App\Http\Controllers\V2\MarketplaceSyncFailureController::class, 'destroy'])->name('v2.marketplace-sync-failures.destroy');
    });
});

