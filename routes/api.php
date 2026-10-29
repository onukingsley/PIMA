<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthenticationController;
use App\Http\Controllers\ManagerController;
use App\Http\Controllers\SalesController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/login',[AuthenticationController::class,'login']);
Route::post('/logout',[AuthenticationController::class,'logout'])->middleware('auth:sanctum');
Route::post('/register',[AuthenticationController::class,'register']);

// sales Dashboard

Route::get('/getSalesOverview',[SalesController::class,'getSalesOverview'])->middleware('auth:sanctum');
Route::get('/getSalesDetails',[SalesController::class,'getSalesDetails'])->middleware('auth:sanctum');

Route::post('/salesDrug',[SalesController::class,'salesDrugUpdated'])->middleware('auth:sanctum');
//Route::post('/getSalesOverview',[SalesController::class,'getSalesOverview'])->middleware('auth:sanctum');
Route::post('/newDrugRequest',[SalesController::class,'newDrugRequest'])->middleware('auth:sanctum');
Route::post('/restockRequest',[SalesController::class,'restockRequest'])->middleware('auth:sanctum');


// manager dashboard
Route::get('/getManagerOverview',[ManagerController::class,'getManagerOverview']);

Route::post('/managerSalesDrug',[ManagerController::class,'managerSalesDrug']);
Route::post('/restockApproval',[ManagerController::class,'restockApproval']);
Route::post('/approveNewDrug',[ManagerController::class,'approveNewDrug']);
Route::post('/paymentEntry',[ManagerController::class,'paymentEntry']);
Route::post('/editPayment',[ManagerController::class,'editPayment']);
Route::post('/updateDrugPrice',[ManagerController::class,'updateDrugPrice']);
Route::post('/managerNewDrugRequest',[ManagerController::class,'managerNewDrugRequest']);
Route::post('/addDrugBatch',[ManagerController::class,'addDrugBatch']);


// admin Dashboard

Route::get('/getAdminOverview',[AdminController::class,'getAdminOverview']);

Route::post('/adminSalesDrug',[AdminController::class,'adminSalesDrug']);
Route::post('/adminRestockApproval',[AdminController::class,'adminRestockApproval']);
Route::post('/adminApproveNewDrug',[AdminController::class,'adminApproveNewDrug']);
Route::post('/adminPaymentEntry',[AdminController::class,'adminPaymentEntry']);
Route::post('/adminEditPayment',[AdminController::class,'adminEditPayment']);
Route::post('/adminUpdateDrugPrice',[AdminController::class,'updateDrugPrice']);
Route::post('/adminNewDrugRequest',[AdminController::class,'adminNewDrugRequest']);
Route::post('/adminAddDrugBatch',[AdminController::class,'adminAddDrugBatch']);

