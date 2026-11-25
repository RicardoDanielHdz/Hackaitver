<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\EmergencyDispatchController;
use App\Http\Controllers\RoleViewController;
use App\Http\Controllers\SlottingController;
use App\Http\Controllers\UserManagementController;
use Illuminate\Support\Facades\Route;

// Rutas de autenticación (Únicamente por correo y contraseña)
Route::get('/', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.post');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// Rutas protegidas por sesión y control estricto de rol (RBAC)
Route::middleware('auth')->group(function () {
    Route::get('/gerente', [RoleViewController::class, 'gerente'])
        ->middleware('role:gerente')
        ->name('dashboard.gerente');

    Route::post('/gerente/usuarios', [UserManagementController::class, 'store'])
        ->middleware('role:gerente')
        ->name('gerente.users.store');

    Route::patch('/gerente/usuarios/{user}/estatus', [UserManagementController::class, 'toggleStatus'])
        ->middleware('role:gerente')
        ->name('gerente.users.toggle');

    Route::post('/gerente/emergencias/{emergencyDispatchRequest}/aprobar', [EmergencyDispatchController::class, 'approve'])
        ->middleware('role:gerente')
        ->name('gerente.emergencies.approve');

    Route::post('/gerente/emergencias/{emergencyDispatchRequest}/rechazar', [EmergencyDispatchController::class, 'reject'])
        ->middleware('role:gerente')
        ->name('gerente.emergencies.reject');

    Route::get('/embarques', [RoleViewController::class, 'embarques'])
        ->middleware('role:jefe_embarque,gerente')
        ->name('dashboard.embarques');

    Route::get('/embarques/emergencias', [EmergencyDispatchController::class, 'index'])
        ->middleware('role:jefe_embarque,operador,gerente')
        ->name('embarques.emergencies.index');

    Route::post('/embarques/emergencias', [EmergencyDispatchController::class, 'store'])
        ->middleware('role:jefe_embarque,operador,gerente')
        ->name('embarques.emergencies.store');

    Route::get('/recepcion', [RoleViewController::class, 'recepcion'])
        ->middleware('role:operador,gerente')
        ->name('dashboard.recepcion');

    Route::get('/proveedor', [RoleViewController::class, 'proveedor'])
        ->middleware('role:proveedor,gerente')
        ->name('dashboard.proveedor');

    Route::post('/proveedor/programar-entrega', [SlottingController::class, 'storeSupplierDelivery'])
        ->middleware('role:proveedor,gerente')
        ->name('wms.supplier.schedule');

    Route::get('/sucursal', [RoleViewController::class, 'sucursal'])
        ->middleware('role:sucursal,gerente')
        ->name('dashboard.sucursal');

    // Rutas compartidas con Proveedor (Alta de producto e impresión de etiquetas QR)
    Route::get('/acomodo/etiqueta-qr', [SlottingController::class, 'productQrLabel'])
        ->middleware('role:operador,proveedor,jefe_embarque,gerente')
        ->name('wms.qr.label');

    Route::post('/acomodo/productos', [SlottingController::class, 'storeProduct'])
        ->middleware('role:operador,proveedor,gerente')
        ->name('wms.products.store');

    // Módulo interno de Almacén: Motor de Acomodo, Racks y Órdenes de Salida
    Route::prefix('acomodo')
        ->name('wms.')
        ->middleware('role:operador,jefe_embarque,gerente')
        ->group(function () {
            Route::get('/', [SlottingController::class, 'index'])->name('index');
            Route::get('/sucursal/{branch}/estado', [SlottingController::class, 'state'])->name('state');
            Route::post('/recomendar', [SlottingController::class, 'recommend'])->name('recommend');
            Route::post('/recibir', [SlottingController::class, 'receive'])->name('receive');
            Route::post('/recibir-lote', [SlottingController::class, 'receiveBatch'])->name('receive-batch');
            Route::post('/tarimas/{pallet}/ubicar', [SlottingController::class, 'slotPallet'])->name('pallets.slot');
            Route::post('/llegadas-programadas', [SlottingController::class, 'storePlannedArrival'])->name('arrivals.store');
            Route::post('/ordenes-salida', [SlottingController::class, 'storeOutboundOrder'])->name('outbound.store');
            Route::get('/ordenes-salida/{outboundOrder}/manifiesto', [SlottingController::class, 'manifest'])->name('outbound.manifest');
            Route::post('/escanear-qr', [SlottingController::class, 'scanQr'])->name('qr.scan');
            Route::get('/etiquetas-laser', [SlottingController::class, 'internalLaserLabels'])->name('qr.laser-sheet');
            Route::post('/reiniciar-demo', [SlottingController::class, 'resetDemo'])->name('reset-demo');
        });
});
