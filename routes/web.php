<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\RoleViewController;
use App\Http\Controllers\SlottingController;
use Illuminate\Support\Facades\Route;

// Rutas de autenticación
Route::get('/', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.post');
Route::get('/login/quick/{role}', [AuthController::class, 'quickLogin'])->name('login.quick');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// Rutas protegidas por sesión
Route::middleware('auth')->group(function () {
    Route::get('/gerente', [RoleViewController::class, 'gerente'])->name('dashboard.gerente');
    Route::get('/embarques', [RoleViewController::class, 'embarques'])->name('dashboard.embarques');
    Route::get('/recepcion', [RoleViewController::class, 'recepcion'])->name('dashboard.recepcion');
    Route::get('/proveedor', [RoleViewController::class, 'proveedor'])->name('dashboard.proveedor');
    Route::post('/proveedor/programar-entrega', [SlottingController::class, 'storeSupplierDelivery'])->name('wms.supplier.schedule');
    Route::get('/sucursal', [RoleViewController::class, 'sucursal'])->name('dashboard.sucursal');

    // Módulo dedicado: Motor de Acomodo, Llegadas y Órdenes de Salida (WMS Papelería 72 SKUs)
    Route::prefix('acomodo')->name('wms.')->group(function () {
        Route::get('/', [SlottingController::class, 'index'])->name('index');
        Route::get('/sucursal/{branch}/estado', [SlottingController::class, 'state'])->name('state');
        Route::post('/recomendar', [SlottingController::class, 'recommend'])->name('recommend');
        Route::post('/recibir', [SlottingController::class, 'receive'])->name('receive');
        Route::post('/recibir-lote', [SlottingController::class, 'receiveBatch'])->name('receive-batch');
        Route::post('/tarimas/{pallet}/ubicar', [SlottingController::class, 'slotPallet'])->name('pallets.slot');
        Route::post('/llegadas-programadas', [SlottingController::class, 'storePlannedArrival'])->name('arrivals.store');
        Route::post('/ordenes-salida', [SlottingController::class, 'storeOutboundOrder'])->name('outbound.store');
        Route::get('/ordenes-salida/{outboundOrder}/manifiesto', [SlottingController::class, 'manifest'])->name('outbound.manifest');
        Route::get('/etiqueta-qr', [SlottingController::class, 'productQrLabel'])->name('qr.label');
        Route::post('/escanear-qr', [SlottingController::class, 'scanQr'])->name('qr.scan');
        Route::post('/productos', [SlottingController::class, 'storeProduct'])->name('products.store');
        Route::post('/reiniciar-demo', [SlottingController::class, 'resetDemo'])->name('reset-demo');
    });
});
