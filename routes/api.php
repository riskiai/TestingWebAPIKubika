<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SPBController;
use App\Http\Controllers\TaxController;
use App\Http\Controllers\DivisiController;
use App\Http\Controllers\ProdukController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\KategoriController;
use App\Http\Controllers\ManPowerController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\SpbStatusController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\User\UsersController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\ContactTypeController;
use App\Http\Controllers\LogsKubikaController;
use App\Http\Controllers\SpbCategoryController;
use App\Http\Controllers\PurchaseStatusController;
use App\Http\Controllers\PurchaseCategoryController;

Route::prefix('auth')->group(function () {
    Route::post('login', LoginController::class);

    Route::post('logout', LogoutController::class)
        ->middleware('auth:sanctum');
});


Route::get('show/{id}', [SPBController::class, 'showNotLogin']);
Route::post('store-notlogin', [UsersController::class, 'storeNotLogin']);
Route::put('updatepassword-email', [UsersController::class, 'UpdatePasswordWithEmail']);
Route::put('updatepassword-emailtoken', [UsersController::class, 'UpdatePasswordWithEmailToken']);
Route::put('verify-token', [UsersController::class, 'verifyTokenAndUpdatePassword']);
Route::get('cektoken', [UsersController::class, 'cekToken']);

Route::middleware(['auth:sanctum'])->group(function () {
     // Users
     Route::prefix('user')->group(function () {
        Route::get('/', [UsersController::class, 'index']);
        Route::get('all', [UsersController::class, 'usersAll']);
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


    Route::get('/logs-kubika', [LogsKubikaController::class, 'index']);

      // Divisi Tenaga Kerja Di Role Users
    Route::get('divisi', [DivisiController::class, 'index']);
    Route::get('divisiall', [DivisiController::class, 'divisiall']);
    Route::post('divisi-store', [DivisiController::class, 'store']);
    Route::get('divisi/{id}', [DivisiController::class, 'show']);
    Route::put('divisi-update/{id}', [DivisiController::class, 'update']);
    Route::delete('divisi-destroy/{id}', [DivisiController::class, 'destroy']);

     /* Contact */
     Route::get('contact', [ContactController::class, 'index']);
     Route::get('contact/name/all', [ContactController::class, 'companyAll']);
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
     Route::get('kategoriall', [KategoriController::class, 'categoryall']);
     Route::post('kategori-store', [KategoriController::class, 'store']);
     Route::get('kategori/{id}', [KategoriController::class, 'show']);
     Route::put('kategori-update/{id}', [KategoriController::class, 'update']);
     Route::delete('kategori-destroy/{id}', [KategoriController::class, 'destroy']);

     // Products
     Route::get('product', [ProdukController::class, 'index']);
     Route::get('product-all', [ProdukController::class, 'produkAll']);
     Route::post('product-store', [ProdukController::class, 'store']);
     Route::get('product/{id}', [ProdukController::class, 'show']);
     Route::put('product-update/{id}', [ProdukController::class, 'update']);
     Route::delete('product-destroy/{id}', [ProdukController::class, 'destroy']);

     // Project
     Route::get('project', [ProjectController::class, 'index']);
     Route::get('/projects/names', [ProjectController::class, 'indexall']);
     Route::get('projectall', [ProjectController::class, 'projectall']);
     Route::get('projects/counting', [ProjectController::class, 'counting']);
     Route::get('project/{id}', [ProjectController::class, 'show']);
     Route::get('project/invoice/{id}', [ProjectController::class, 'invoice']);
     Route::post('project/create-informasi', [ProjectController::class, 'createInformasi']);
     Route::post('project/payment-termin/{id}', [ProjectController::class, 'paymentTermin']);
     Route::put('project/update-termin/{id}', [ProjectController::class, 'updateTermin']);
     Route::delete('project/delete-termin/{id}', [ProjectController::class, 'deleteTermin']);

     Route::put('project/accept/{id}', [ProjectController::class, 'accept']);
     Route::put('project/closed/{id}', [ProjectController::class, 'closed']);
     Route::put('project/bonus/{id}', [ProjectController::class, 'bonus']);
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
    Route::get('spbprojectprint', [SPBController::class, 'indexall']);
    Route::get('spbproject/counting', [SPBController::class, 'counting']); 
    Route::get('spbproject/countingspb', [SPBController::class, 'countingspb']);
    Route::get('spbproject/countingspbusersproject', [SPBController::class, 'countingspbusers']);
    Route::get('spbproject/countingnonproject', [SPBController::class, 'countingspbnonprojects']);
    Route::post('spbproject/create-spb', [SPBController::class, 'store']);
    Route::put('addspbproject/toproject/{id}', [SPBController::class, 'addspbtoproject']);
    Route::put('spbproject/update-spb/{id}', [SPBController::class, 'update']);
 
    Route::get('spbproject/{id}', [SPBController::class, 'show']);
    Route::delete('spbproject/destroy/{id}', [SPBController::class, 'destroy']);
    Route::put('spbproject/accept/{id}', [SPBController::class, 'accept']);
    Route::put('spbproject/reject/{id}', [SPBController::class, 'reject']);
    Route::put('spbproject/reject-produk/{id}', [SPBController::class, 'rejectproduk']);

    Route::delete('spbproject/delete-termin/{id}', [SPBController::class, 'deleteTermin']);
    Route::put('spbproject/update-termin/{id}', [SPBController::class, 'updateTermin']);
    Route::post('spbproject/add-produk/{id}', [SPBController::class, 'storeProduk']);
    Route::put('spbproject/activate-produk/{id}', [SPBController::class, 'activateproduk']);
    Route::put('spbproject/activate/{id}', [SPBController::class, 'activate']);
    Route::put('spbproject/accept-produk/{id}', [SPBController::class, 'acceptproduk']);
    Route::delete('spbproject/delete-produk/{id}', [SPBController::class, 'deleteProduk']);
    Route::put('spbproject/update-produk/{id}', [SPBController::class, 'updateproduk']);
    Route::put('spbproject/payment-produk/{id}', [SPBController::class, 'paymentproduk']);

    Route::put('spbproject/undo/{id}', [SPBController::class, 'undo']);
    Route::put('spbproject/request/{id}', [SPBController::class, 'request']);
    Route::put('spbproject/payment/{id}', [SPBController::class, 'payment']);
    Route::put('spbproject/payment-vendor/{id}', [SPBController::class, 'paymentVendor']);
    Route::put('spbproject/update-payment/{id}', [SPBController::class, 'updatepayment']);
    Route::put('spbproject/accSpbProject/{id}', [SPBController::class, 'accSpbProject']);
    Route::delete('spbproject/delete-document/{id}', [SPBController::class, 'deleteDocument']);
    Route::put('spbproject/knowmarketing/{id}', [SPBController::class, 'knowmarketing']);
    Route::put('spbproject/knowmarkepalagudang/{id}', [SPBController::class, 'knowmarkepalagudang']);
    Route::put('spbproject/menyetujuiowner/{id}', [SPBController::class, 'menyetujuiowner']);

     // Report 
     Route::get('spbproject-report-pph', [ReportController::class, 'reportPPH']);
     Route::get('spbproject-report-ppn', [ReportController::class, 'reportPPN']);
     Route::get('spbproject-report-paid', [ReportController::class, 'reportPaid']);
     Route::get('manpower-report', [ReportController::class, 'reportManpower']);
     Route::get('project-report', [ReportController::class, 'reportProject']);

    /* Man Power */
    Route::prefix('man-power')->group(function () {
        Route::get('/', [ManPowerController::class, 'index']);
        Route::get('/all', [ManPowerController::class, 'manpowerall']);
        Route::get('/counting', [ManPowerController::class, 'counting']);
        Route::post('/', [ManPowerController::class, 'store']);
        Route::get('/{id}', [ManPowerController::class, 'show']);
        Route::put('/{id}', [ManPowerController::class, 'update']);
        Route::delete('/{id}', [ManPowerController::class, 'destroy']);
    });
});




