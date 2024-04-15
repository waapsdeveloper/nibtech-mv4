<?php

use App\Http\Controllers\ApiCallController;
use App\Http\Controllers\ApiController;
use App\Http\Controllers\ApiReturnController;
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

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::post('2.5', [ApiController::class, 'store']);
Route::get('2.5', [ApiController::class, 'store']);


Route::post('return/notify-abt-zp', [ApiReturnController::class, 'notify_abt_zp']);
Route::get('return/notify-abt-zp', [ApiReturnController::class, 'notify_abt_zp']);


Route::post('call/settlement', [ApiCallController::class, 'settlement']);

Route::get('call/reapprove', [ApiCallController::class, 'reapprove_all']);
Route::get('call/confirm/{id}/{callback?}', [ApiCallController::class, 'confirm_transaction']);
Route::get('call/confirm-settlement/{id}', [ApiCallController::class, 'confirm_settlement']);
Route::get('call/decline/{id}/{callback?}', [ApiCallController::class, 'decline_transaction']);
Route::get('call/decline-settlement/{id}/{callback?}', [ApiCallController::class, 'decline_settlement']);
Route::get('call/callback/{id}', [ApiCallController::class, 'send_transaction_postback']);
