<?php

use App\Http\Controllers\LanguageController;
use App\Http\Controllers\TestingController;
use App\Http\Livewire\Change;
use App\Http\Livewire\Customer;
use App\Http\Livewire\Emptypage;
use App\Http\Livewire\Error404;
use App\Http\Livewire\Error500;
use App\Http\Livewire\Error501;
use App\Http\Livewire\FortnightReturn;
use App\Http\Livewire\Grade;
use App\Http\Livewire\IMEI;
use App\Http\Livewire\Index;
use App\Http\Livewire\Profile;
use App\Http\Livewire\Searching;
use App\Http\Livewire\Signin;
use App\Http\Livewire\Order;
use App\Http\Livewire\Wholesale;
use App\Http\Livewire\Inventory;
use App\Http\Livewire\Issue;
use App\Http\Livewire\Listing;
use App\Http\Livewire\Product;
use App\Http\Livewire\Variation;
use App\Http\Livewire\Process;
use App\Http\Livewire\Payouts;
use App\Http\Livewire\Logout;
use App\Http\Livewire\MoveInventory;
use App\Http\Livewire\Repair;
use App\Http\Livewire\Report;
use App\Http\Livewire\RMA;
use App\Http\Livewire\SalesReturn;
use App\Http\Livewire\Team;
use App\Http\Livewire\Testing;
use Illuminate\Support\Facades\Route;
use App\Http\Middleware\GetAllowedRoutesMiddleware;

use App\Models\Routes_model;
use Livewire\Commands\MoveCommand;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

// Route::get('/', function () {
//     return view('livewire.index');
// });
Route::get('/', Index::class)->name('index');
Route::get('index', Index::class)->name('index');
// Route::post('change', Change::class);
Route::get('error404', Error404::class)->name('error');
Route::get('error500', Error500::class)->name('error');
Route::get('error501', Error501::class)->name('error');
Route::get('profile', Profile::class)->name('profile');
Route::post('profile', Profile::class)->name('profile');
Route::get('signin', Signin::class)->name('login');
Route::post('login', [Signin::class,'login'])->name('signin');
Route::get('logout', Logout::class)->name('signin');

Route::get('purchase', [Order::class,'purchase'])->name('view_purchase');
Route::post('add_purchase', [Order::class,'add_purchase'])->name('add_purchase');
Route::post('add_purchase_item/{id}', [Order::class,'add_purchase_item'])->name('add_purchase_item');
Route::get('delete_order/{id}', [Order::class,'delete_order'])->name('delete_purchase');
Route::get('delete_order_item/{id}', [Order::class,'delete_order_item'])->name('delete_purchase_item');
Route::get('purchase/detail/{id}', [Order::class,'purchase_detail'])->name('purchase_detail');
Route::post('purchase/approve/{id}', [Order::class,'purchase_approve'])->name('purchase_approve');
Route::post('purchase/remove_issues', [Order::class,'remove_issues'])->name('remove_purchase_issues');

Route::get('report', Report::class)->name('view_report');
Route::get('report/export', [Report::class,'export_report'])->name('view_report');

Route::get('return', SalesReturn::class)->name('view_return');
Route::get('add_return', [SalesReturn::class,'add_return'])->name('add_return');
Route::post('add_return_item/{id}', [SalesReturn::class,'add_return_item'])->name('add_return_item');
Route::get('delete_return/{id}', [SalesReturn::class,'delete_return'])->name('delete_return');
Route::get('delete_return_item/{id}', [SalesReturn::class,'delete_return_item'])->name('delete_return_item');
Route::get('return/detail/{id}', [SalesReturn::class,'return_detail'])->name('return_detail');
Route::post('return/ship/{id}', [SalesReturn::class,'return_ship'])->name('return_ship');
Route::post('return/approve/{id}', [SalesReturn::class,'return_approve'])->name('return_approve');

Route::get('repair', Repair::class)->name('view_repair');
Route::post('add_repair', [Repair::class,'add_repair'])->name('add_repair');
Route::post('check_repair_item/{id}', [Repair::class,'check_repair_item'])->name('add_repair_item');
Route::post('receive_repair_item/{id}', [Repair::class,'receive_repair_item'])->name('receive_repair_item');
Route::post('add_repair_item/{id}', [Repair::class,'add_repair_item'])->name('add_repair_item');
Route::post('repair/add_repair_sheet/{id}', [Repair::class,'add_repair_sheet'])->name('add_repair_item');
Route::get('delete_repair/{id}', [Repair::class,'delete_repair'])->name('delete_repair');
Route::get('delete_repair_item/{id}', [Repair::class,'delete_repair_item'])->name('delete_repair_item');
Route::get('repair/detail/{id}', [Repair::class,'repair_detail'])->name('repair_detail');
Route::post('repair/ship/{id}', [Repair::class,'repair_ship'])->name('repair_ship');
Route::post('repair/approve/{id}', [Repair::class,'repair_approve'])->name('repair_approve');
Route::get('export_repair_invoice/{id}', [Repair::class,'export_repair_invoice'])->name('repair_detail');
Route::get('repair/internal', [Repair::class,'internal_repair'])->name('internal_repair');
Route::post('add_internal_repair_item', [Repair::class,'add_internal_repair_item'])->name('internal_repair');

Route::get('wholesale', Wholesale::class)->name('view_wholesale');
Route::post('add_wholesale', [Wholesale::class,'add_wholesale'])->name('add_wholesale');
Route::post('check_wholesale_item/{id}', [Wholesale::class,'check_wholesale_item'])->name('add_wholesale_item');
Route::post('add_wholesale_item/{id}', [Wholesale::class,'add_wholesale_item'])->name('add_wholesale_item');
Route::get('delete_wholesale/{id}', [Wholesale::class,'delete_order'])->name('delete_wholesale');
Route::get('delete_wholesale_item/{id}', [Wholesale::class,'delete_order_item'])->name('delete_wholesale_item');
Route::get('wholesale/detail/{id}', [Wholesale::class,'wholesale_detail'])->name('wholesale_detail');
Route::post('wholesale/update_prices', [Wholesale::class,'update_prices'])->name('update_wholesale_item');
Route::get('export_bulksale_invoice/{id}/{invoice?}', [Wholesale::class,'export_bulksale_invoice'])->name('wholesale_detail');
Route::get('bulksale_email/{id}', [Wholesale::class,'bulksale_email'])->name('wholesale_detail');
Route::post('wholesale/add_wholesale_sheet/{id}', [Wholesale::class,'add_wholesale_sheet'])->name('add_wholesale_item');
Route::post('wholesale/approve/{id}', [Wholesale::class,'wholesale_approve'])->name('wholesale_approve');
Route::post('wholesale/remove_issues', [Wholesale::class,'remove_issues'])->name('remove_wholesale_issues');

Route::get('rma', RMA::class)->name('view_rma');
Route::post('add_rma', [RMA::class,'add_rma'])->name('add_rma');
Route::post('check_rma_item/{id}', [RMA::class,'check_rma_item'])->name('add_rma_item');
Route::post('add_rma_item/{id}', [RMA::class,'add_rma_item'])->name('add_rma_item');
Route::get('delete_rma/{id}', [RMA::class,'delete_order'])->name('delete_rma');
Route::get('delete_rma_item/{id}', [RMA::class,'delete_order_item'])->name('delete_rma_item');
Route::get('rma/detail/{id}', [RMA::class,'rma_detail'])->name('rma_detail');
Route::post('rma/update_prices', [RMA::class,'update_prices'])->name('update_rma_item');
Route::get('export_rma_invoice/{id}', [RMA::class,'export_rma_invoice'])->name('rma_detail');

Route::get('imei', IMEI::class)->name('view_imei');
Route::post('imei/refund/{id}', [IMEI::class,'refund'])->name('refund_imei');

Route::get('issue', Issue::class)->name('view_issue');

Route::get('fortnight_return', FortnightReturn::class)->name('view_fortnight_return');
Route::get('fortnight_return/print', [FortnightReturn::class, 'print'])->name('view_fortnight_return');

Route::get('move_inventory', MoveInventory::class)->name('move_inventory');
Route::post('move_inventory/change_grade', [MoveInventory::class,'change_grade'])->name('move_inventory');
Route::post('move_inventory/delete_move', [MoveInventory::class,'delete_move'])->name('move_inventory');
Route::post('move_inventory/delete_multiple_moves', [MoveInventory::class,'delete_multiple_moves'])->name('move_inventory');

Route::get('testing', Testing::class)->name('testing');

Route::get('order', Order::class)->name('view_order');
Route::get('check_new/{return?}', [Order::class,'updateBMOrdersNew'])->name('view_order');
Route::get('refresh_order', [Order::class,'getapiorders'])->name('view_order');
Route::get('refresh_order/{id}', [Order::class,'getapiorders'])->name('view_order');
Route::get('order/refresh/{id?}', [Order::class,'updateBMOrder'])->name('view_order');
Route::post('order/dispatch/{id}', [Order::class,'dispatch'])->name('dispatch_order');
Route::get('order/track/{id}', [Order::class,'track_order'])->name('view_order');
Route::get('order/delete_item/{id}', [Order::class,'delete_item'])->name('delete_order');
Route::post('order/correction', [Order::class,'correction'])->name('dispatch_order');

Route::post('order/replacement/{london?}', [Order::class,'replacement'])->name('replacement');
Route::get('order/delete_replacement_item/{id}', [Order::class,'delete_replacement_item'])->name('replacement');
Route::get('order/recheck/{id}/{refresh?}', [Order::class,'recheck'])->name('view_order');
Route::post('export_order', [Order::class,'export'])->name('dispatch_order');
Route::get('export_note', [Order::class,'export_note'])->name('dispatch_order');
Route::post('export_label', [Order::class,'export_label'])->name('dispatch_order');
Route::get('export_ordersheet', [Order::class,'export_ordersheet'])->name('dispatch_order');
Route::get('export_invoice/{id}', [Order::class,'export_invoice'])->name('dispatch_order');
Route::get('order/label/{id}', [Order::class,'getLabel'])->name('dispatch_order');

Route::get('sales/allowed', [Order::class,'sales_allowed'])->name('dispatch_admin');
Route::post('order/dispatch_allowed/{id}', [Order::class,'dispatch_allowed'])->name('dispatch_admin');

Route::get('inventory', Inventory::class)->name('view_inventory');
Route::get('inventory/get_products', [Inventory::class,'get_products'])->name('view_inventory');
Route::get('inventory/get_variations/{id}', [Inventory::class,'get_variations'])->name('view_inventory');
Route::post('inventory/export', [Inventory::class,'export'])->name('view_inventory');

Route::get('inventory/start_verification', [Inventory::class,'start_verification'])->name('inventory_verification');
Route::post('inventory/end_verification', [Inventory::class,'end_verification'])->name('inventory_verification');
Route::post('inventory/add_verification_imei/{id}', [Inventory::class,'add_verification_imei'])->name('inventory_verification');

Route::get('belfast_inventory', [Inventory::class,'belfast_inventory'])->name('view_belfast_inventory');
Route::post('belfast_inventory/aftersale_action/{id}/{action}', [Inventory::class,'aftersale_action'])->name('add_return_item');

Route::get('product', Product::class)->name('view_product');
Route::post('add_product', [Product::class,'add_product'])->name('add_product');
Route::post('product/update_product/{id}', [Product::class,'update_product'])->name('update_product');

Route::get('variation', Variation::class)->name('view_variation');
Route::post('variation/update_product/{id}', [Variation::class,'update_product'])->name('update_variation');

Route::get('listing', Listing::class)->name('view_listing');

Route::get('process', Process::class)->name('view_process');

Route::get('team', Team::class)->name('view_team');
Route::get('add-member', [Team::class,'add_member'])->name('add_member');
Route::post('insert-member', [Team::class,'insert_member'])->name('add_member');
Route::get('update-status/{id}', [Team::class,'update_status'])->name('edit_member');
Route::get('edit-member/{id}', [Team::class,'edit_member'])->name('edit_member');
Route::post('update-member/{id}', [Team::class,'update_member'])->name('edit_member');

Route::get('customer', Customer::class)->name('view_customer');
Route::get('add-customer', [Customer::class,'add_customer'])->name('add_customer');
Route::post('insert-customer', [Customer::class,'insert_customer'])->name('add_customer');
Route::get('update-status/{id}', [Customer::class,'update_status'])->name('edit_customer');
Route::get('edit-customer/{id}', [Customer::class,'edit_customer'])->name('edit_customer');
Route::post('update-customer/{id}', [Customer::class,'update_customer'])->name('edit_customer');

Route::get('grade', Grade::class)->name('view_grade');
Route::get('add-grade', [Grade::class,'add_grade'])->name('add_grade');
Route::post('insert-grade', [Grade::class,'insert_grade'])->name('add_grade');
Route::get('update-status/{id}', [Grade::class,'update_status'])->name('edit_grade');
Route::get('edit-grade/{id}', [Grade::class,'edit_grade'])->name('edit_grade');
Route::post('update-grade/{id}', [Grade::class,'update_grade'])->name('edit_grade');

Route::get('get_permissions/{id}', [Team::class,'get_permissions'])->name('view_permissions');
Route::post('toggle_role_permission/{roleId}/{permissionId}/{isChecked}', [Team::class, 'toggle_role_permission'])->name('change_permission');

Route::post('change', [Change::class,'change_password'])->name('profile');
Route::get('OTP/{any}', [Change::class,'otp'])->name('profile');
Route::get('page', [Change::class,'page'])->name('profile');
Route::post('QomeBa27WU', [Change::class,'reset_page'])->name('profile');
Route::post('reset', [Change::class,'reset_pass'])->name('profile');


use App\Http\Controllers\GoogleController;

Route::get('oauth2/google', [GoogleController::class, 'redirectToGoogle'])->name('google.auth');
Route::get('oauth2/callback', [GoogleController::class, 'handleGoogleCallback'])->name('google.callback');

use App\Http\Controllers\ExchangeRateController;

Route::get('/exchange-rates', [ExchangeRateController::class, 'index']);
