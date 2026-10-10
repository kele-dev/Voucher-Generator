<?php

use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\BatchController;
use App\Http\Controllers\BrandController;
use App\Http\Controllers\CampaignController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EditorController;
use App\Http\Controllers\OutletController;
use App\Http\Controllers\TemplateController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VoucherController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::get('editor', [EditorController::class, 'index'])->name('editor.index');
    Route::get('editor/{template}', [EditorController::class, 'show'])->name('editor.show');

    Route::resource('brands', BrandController::class)->except('show');
    Route::get('brands/{brand}/template-image/{kind}', [BrandController::class, 'templateImage'])->name('brands.template-image');
    Route::resource('outlets', OutletController::class)->except('show');
    Route::resource('users', UserController::class)->except('show');

    Route::resource('templates', TemplateController::class)->except('show');
    Route::controller(TemplateController::class)->prefix('templates/{template}')->name('templates.')->group(function () {
        Route::get('image', 'image')->name('image');
        Route::get('sample-image', 'sampleImage')->name('sample-image');
        Route::post('sample-image', 'uploadSampleImage')->name('sample-image.upload');
        Route::get('editor', 'editor')->name('editor');
        Route::put('fields', 'updateFields')->name('fields');
        Route::post('preview', 'preview')->middleware('throttle:30,1')->name('preview');
        Route::post('versions', 'createVersion')->name('versions');
    });

    Route::post('campaigns/preview', [CampaignController::class, 'preview'])->middleware('throttle:40,1')->name('campaigns.preview');
    Route::resource('campaigns', CampaignController::class);
    Route::patch('campaigns/{campaign}/status', [CampaignController::class, 'updateStatus'])->name('campaigns.status');
    Route::get('campaigns/{campaign}/preview', [CampaignController::class, 'savedPreview'])->middleware('throttle:40,1')->name('campaigns.saved-preview');
    Route::get('campaigns/{campaign}/product-image', [CampaignController::class, 'productImage'])->name('campaigns.product-image');
    Route::post('campaigns/{campaign}/batches', [BatchController::class, 'store'])->middleware('throttle:20,1')->name('campaigns.batches.store');

    Route::get('batches/{batch}', [BatchController::class, 'show'])->name('batches.show');
    Route::post('batches/{batch}/export', [BatchController::class, 'export'])->middleware('throttle:10,1')->name('batches.export');
    Route::post('batches/{batch}/retry', [BatchController::class, 'retry'])->middleware('throttle:10,1')->name('batches.retry');
    Route::get('batches/{batch}/download', [BatchController::class, 'download'])->name('batches.download');

    Route::get('vouchers', [VoucherController::class, 'index'])->name('vouchers.index');
    Route::get('vouchers/{voucher}', [VoucherController::class, 'show'])->name('vouchers.show');
    Route::get('vouchers/{voucher}/download', [VoucherController::class, 'download'])->middleware('throttle:60,1')->name('vouchers.download');
    Route::post('vouchers/{voucher}/void', [VoucherController::class, 'void'])->name('vouchers.void');

    Route::get('activity', [ActivityLogController::class, 'index'])->name('activity.index');
});
