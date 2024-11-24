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
use App\Http\Controllers\ProdukController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\SpbCategoryController;
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
     Route::get('project/create-informasi', [ProjectController::class, 'createInformasi']);

     // SPB PROJECTS
      // end point puchase category
    Route::prefix('spbproject-category')->group(function () {
        Route::get('/', [SpbCategoryController::class, 'index']);
        Route::get('/{id}', [SpbCategoryController::class, 'show']);
    });

    // end point puchase status
    Route::prefix('spbproject-status')->group(function () {
        Route::get('/', [SpbStatusController::class, 'index']);
        Route::get('/{id}', [SpbStatusController::class, 'show']);
    });
});