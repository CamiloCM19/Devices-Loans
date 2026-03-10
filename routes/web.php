<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\HistorialController;

Route::get('/', function () {
    return redirect('/inventory');
});

Route::get('/inventory', [InventoryController::class, 'index'])->name('inventory.index');
Route::post('/inventory/scan', [InventoryController::class, 'procesarInput'])->name('inventory.scan');
Route::post('/inventory/scan/esp', [InventoryController::class, 'procesarInputEsp'])->name('inventory.scan.esp');
Route::get('/inventory/scan/esp/ping', [InventoryController::class, 'pingEsp'])->name('inventory.scan.esp.ping');
Route::get('/historial', [HistorialController::class, 'index'])->name('historial');

// Registration Routes
Route::get('/inventory/register/{nfc_id}', [InventoryController::class, 'showRegister'])->name('inventory.register');
Route::post('/inventory/register/student', [InventoryController::class, 'storeStudent'])->name('inventory.storeStudent');
Route::post('/inventory/register/camera', [InventoryController::class, 'storeCamera'])->name('inventory.storeCamera');
