<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Admin Controller Start

use App\Http\Controllers\Admin\AdminLogin;
use App\Http\Controllers\Admin\ProcessOrganisation;

// Admin Controller End

// User Controller Start

use App\Http\Controllers\Organisation\ProcessUserLogin;
use App\Http\Controllers\Organisation\ProcessMaster;

// User Controller End

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/


// Admin Route Define Area

Route::post('/Admin/ProcessLogin',[AdminLogin::class,'process_admin_login'])->middleware('api_access');

Route::group([
    'middleware' => ['auth:sanctum',]
],function(){

// Dashboard Route

Route::get('/Admin/GetSideBar',[AdminLogin::class,'get_admin_sidebar']);
Route::get('/Admin/LogOut',[AdminLogin::class,'process_logout']);

// End Dashboard Route

// Process Organisation

Route::post('/Admin/ProcessOrganisation/AddOrg',[ProcessOrganisation::class,'process_org']);
Route::get('/Admin/ProcessOrganisation/OrgList',[ProcessOrganisation::class,'get_org_list']);
Route::get('/Admin/ProcessOrganisation/GetOrgModule',[ProcessOrganisation::class,'get_org_module']);
Route::get('/Admin/ProcessOrganisation/GetActiveModule/{org_id}',[ProcessOrganisation::class,'get_org_active_module']);
Route::post('/Admin/ProcessOrganisation/MapModule',[ProcessOrganisation::class,'process_org_module']);
Route::get('/Admin/ProcessOrganisation/GetActiveRental/{org_id}',[ProcessOrganisation::class,'get_active_rental']);
Route::post('/Admin/ProcessOrganisation/PostRental',[ProcessOrganisation::class,'process_org_rental']);
Route::get('/Admin/ProcessOrganisation/GetYearDate/{org_id}',[ProcessOrganisation::class,'get_year_start_date']);
Route::post('/Admin/ProcessOrganisation/AddFinYear',[ProcessOrganisation::class,'process_org_fin_year']);
Route::post('/Admin/ProcessOrganisation/AddAdminUser',[ProcessOrganisation::class,'process_org_admin_user']);

// End Process Organisation

});


// End Admin Route Area


// User Route Area

Route::post('/User/ProcessLogin',[ProcessUserLogin::class,'process_user_login'])->middleware('api_access');

Route::group([
    'middleware' => ['auth:sanctum',]
],function(){

    // Dashboard Route

    Route::get('/Org/GetSidebar/{org_id}',[ProcessUserLogin::class,'get_user_sidebar']);
    Route::get('/Org/LogOut',[ProcessUserLogin::class,'process_logout']);

    // End Dashboard Route

    // Master Route Start

    Route::post('/Org/Master/AddCatagory',[ProcessMaster::class,'process_catagory']);
    Route::get('/Org/Master/GetCatagory/{org_id}',[ProcessMaster::class,'get_catagory_list']);
    Route::put('/Org/Master/UpdateCatagory',[ProcessMaster::class,'update_catagory']);
    Route::poest('/Org/Master/AddModel',[ProcessMaster::class,'process_item_model']);
    Route::get('/Org/Master/GetModel/{org_id}',[ProcessMaster::class,'get_model_list']);
    Route::put('/Org/Master/UpdateModel',[ProcessMaster::class,'update_model']);
    Route::get('/Org/Master/GetCatagoryModel/{org_id}/{cat_id}',[ProcessMaster::class,'get_catagory_model']);
    Route::post('/Org/Master/AddSize',[ProcessMaster::class,'process_item_size']);
    Route::get('/Org/Master/GetSize/{org_id}',[ProcessMaster::class,'get_size_list']);
    Route::get('/Org/Master/GetModuleSize/{org_id}/{mod_id}',[ProcessMaster::class,'get_module_size']);
    Route::put('/Org/Master/UpdateSize',[ProcessMaster::class,'update_item_size']);
    Route::post('/Org/Master/AddUnit',[ProcessMaster::class,'process_item_unit']);
    Route::get('/Org/Master/GetUnitList/{org_id}',[ProcessMaster::class,'get_unit_list']);
    Route::put('/Org/Master/UpdateUnit',[ProcessMaster::class,'update_unit']);
    Route::post('/Org/Master/AddSizeColor',[ProcessMaster::class,'process_size_color']);
    Route::get('/Org/Master/GetSizeColor/{org_id}',[ProcessMaster::class,'get_size_color_list']);
    Route::put('/Org/Master/UpdateSizeColor',[ProcessMaster::class,'update_size_color']);

    // End Master Route

});

// User Route Area End Here