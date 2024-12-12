<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TaxController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\User\UsersController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\ContactTypeController;
use App\Http\Controllers\DivisiController;
use App\Http\Controllers\KategoriController;
use App\Http\Controllers\ManPowerController;
use App\Http\Controllers\ProdukController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\PurchaseCategoryController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\PurchaseStatusController;
use App\Http\Controllers\SpbCategoryController;
use App\Http\Controllers\SPBController;
use App\Http\Controllers\SpbStatusController;

Route::prefix('auth')->group(function () {
    Route::post('login', LoginController::class);

    Route::post('logout', LogoutController::class)
        ->middleware('auth:sanctum');
});

Route::middleware(['auth:sanctum'])->group(function () {
     // Users
     Route::prefix('user')->group(function () {
        Route::get('/', [UsersController::class, 'index']);
        Route::get('me', function (Request $request) {
            // dd($request->user());
            return $request->user();
        });
        Route::get('/{id}', [UsersController::class, 'show']);
        Route::post('store', [UsersController::class, 'store']);
        Route::put('update/{id}', [UsersController::class, 'update']);
        Route::put('/reset-password/{id}', [UsersController::class, 'resetPassword']);
        Route::put('update-password', [UsersController::class, 'updatepassword']);
        Route::delete('destroy/{id}', [UsersController::class, 'destroy']);
     });

      // Divisi Tenaga Kerja Di Role Users
    Route::get('divisi', [DivisiController::class, 'index']);
    Route::post('divisi-store', [DivisiController::class, 'store']);
    Route::get('divisi/{id}', [DivisiController::class, 'show']);
    Route::put('divisi-update/{id}', [DivisiController::class, 'update']);
    Route::delete('divisi-destroy/{id}', [DivisiController::class, 'destroy']);

     /* Contact */
     Route::get('contact', [ContactController::class, 'index']);
     Route::post('contact-store', [ContactController::class, 'store']);
     Route::post('contact-update/{id}', [ContactController::class, 'update']);
     Route::get('contact/{id}', [ContactController::class, 'show']);
     Route::get('contactall', [ContactController::class, 'contactall']);
     Route::get('contact-showtype', [ContactController::class, 'showByContactType']);
     Route::delete('contact-destroy/{id}', [ContactController::class, 'destroy']);

     // ContactType
     Route::prefix('contact-type')->group(function () {
        Route::get('/', [ContactTypeController::class, 'index']);
        Route::get('/{id}', [ContactTypeController::class, 'show']);
    });

     // Tax
     Route::get('tax', [TaxController::class, 'index']);
     Route::post('tax-store', [TaxController::class, 'store']);
     Route::get('tax/{id}', [TaxController::class, 'show']);
     Route::put('tax-update/{id}', [TaxController::class, 'update']);
     Route::delete('tax-destroy/{id}', [TaxController::class, 'destroy']);

     // Kategori
     Route::get('kategori', [KategoriController::class, 'index']);
     Route::post('kategori-store', [KategoriController::class, 'store']);
     Route::get('kategori/{id}', [KategoriController::class, 'show']);
     Route::put('kategori-update/{id}', [KategoriController::class, 'update']);
     Route::delete('kategori-destroy/{id}', [KategoriController::class, 'destroy']);

     // Products
     Route::get('product', [ProdukController::class, 'index']);
     Route::post('product-store', [ProdukController::class, 'store']);
     Route::get('product/{id}', [ProdukController::class, 'show']);
     Route::put('product-update/{id}', [ProdukController::class, 'update']);
     Route::delete('product-destroy/{id}', [ProdukController::class, 'destroy']);

     // Project
     Route::get('project', [ProjectController::class, 'index']);
     Route::get('projectall', [ProjectController::class, 'projectall']);
     Route::get('projects/counting', [ProjectController::class, 'counting']);
     Route::get('project/{id}', [ProjectController::class, 'show']);
     Route::post('project/create-informasi', [ProjectController::class, 'createInformasi']);
     Route::put('project/accept/{id}', [ProjectController::class, 'accept']);
     Route::put('project/reject/{id}', [ProjectController::class, 'reject']);
     Route::put('projects/update-pengguna-muatan/{id}', [ProjectController::class, 'UpdatePenggunaMuatan']);
     Route::put('projects/update_lengkap/{id}', [ProjectController::class, 'UpdateLengkap']);
     Route::put('projects/update/{id}', [ProjectController::class, 'update']);
     Route::delete('projects/delete/{id}', [ProjectController::class, 'destroy']);

    // SPB PROJECTS
    // end point SPB PROJECT CATEGORY
    Route::prefix('spbproject-category')->group(function () {
        Route::get('/', [SpbCategoryController::class, 'index']);
        Route::get('/{id}', [SpbCategoryController::class, 'show']);
    });

    // end point SPB PROJECT STATUS
    Route::prefix('spbproject-status')->group(function () {
        Route::get('/', [SpbStatusController::class, 'index']);
        Route::get('/{id}', [SpbStatusController::class, 'show']);
    });

    Route::get('spbproject', [SPBController::class, 'index']);
    Route::post('spbproject/create-spb', [SPBController::class, 'store']);
    Route::put('addspbproject/toproject/{id}', [SPBController::class, 'addspbtoproject']);
    Route::post('spbproject/update-spb/{id}', [SPBController::class, 'update']);
    Route::get('spbproject/{id}', [SPBController::class, 'show']);
    Route::delete('spbproject/destroy/{id}', [SPBController::class, 'destroy']);
    Route::put('spbproject/accept/{id}', [SPBController::class, 'accept']);
    Route::put('spbproject/reject/{id}', [SPBController::class, 'reject']);
    Route::put('spbproject/activate/{id}', [SPBController::class, 'activate']);
    Route::put('spbproject/undo/{id}', [SPBController::class, 'undo']);
    Route::put('spbproject/request/{id}', [SPBController::class, 'request']);
    Route::put('spbproject/payment/{id}', [SPBController::class, 'payment']);
    Route::put('spbproject/accSpbProject/{id}', [SPBController::class, 'accSpbProject']);
    Route::put('spbproject/knowmarketing/{id}', [SPBController::class, 'knowmarketing']);
    Route::put('spbproject/knowmarkepalagudang/{id}', [SPBController::class, 'knowmarkepalagudang']);
    Route::put('spbproject/menyetujuiowner/{id}', [SPBController::class, 'menyetujuiowner']);

    // Purchase
    // end point Purchase Category
    Route::prefix('purchase-category')->group(function () {
        Route::get('/', [PurchaseCategoryController::class, 'index']);
        Route::get('/{id}', [PurchaseCategoryController::class, 'show']);
    });

    // end point Purchase Status
    Route::prefix('purchase-status')->group(function () {
        Route::get('/', [PurchaseStatusController::class, 'index']);
        Route::get('/{id}', [PurchaseStatusController::class, 'show']);
    });

    Route::get('purchase', [PurchaseController::class, 'index']);
    Route::get('purchase/{id}', [PurchaseController::class, 'show']);
    Route::get('purchase-counting', [PurchaseController::class, 'counting']);
    Route::post('purchase-create', [PurchaseController::class, 'store']);
    Route::put('purchase-update/{id}', [PurchaseController::class, 'update']);
    Route::delete('purchase-destroy/{id}', [PurchaseController::class, 'destroy']);


    Route::prefix('man-power')->group(function () {
        Route::get('/', [ManPowerController::class, 'index']);
        Route::post('/', [ManPowerController::class, 'store']);
        Route::get('/{id}', [ManPowerController::class, 'show']);
        Route::put('/{id}', [ManPowerController::class, 'update']);
        Route::delete('/{id}', [ManPowerController::class, 'destroy']);
    });
});
