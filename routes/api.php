<?php

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

// Route::get('/test', function (Request $request) {
//     return response()->json('Hello');
// });
Route::group(['middleware' => ['auth:sanctum']], function () {
    Route::resource('/testing', TestingController::class);
});
