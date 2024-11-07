<?php

use App\Http\Controllers\ApiRequestController;
use App\Http\Controllers\InternalApiController;
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
Route::group(['middleware' => ['auth:sanctum']], function () {
    // return response()->json('Hello');
    Route::resource('/request', ApiRequestController::class);
    // Route::post('request', [ApiRequestController::class, 'store']);
});

Route::group(['middleware' => ['internal.only']], function () {
    Route::get('/internal/get_variations', [InternalApiController::class, 'get_variations']);
    Route::get('/internal/get_variation_available_stocks/{id}', [InternalApiController::class, 'get_variation_available_stocks']);
    Route::get('/internal/get_updated_quantity/{id}', [InternalApiController::class, 'getUpdatedQuantity']);
    Route::get('/internal/get_competitors/{id}/{no_check?}', [InternalApiController::class, 'getCompetitors']);
    Route::get('/internal/inventory_get_vendor_wise_average', [InternalApiController::class, 'inventoryGetVendorWiseAverage']);
    Route::get('/internal/inventory_get_average_cost', [InternalApiController::class, 'inventoryGetAverageCost']);



});
