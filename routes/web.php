<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HardwareTelemetryController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\HistorialController;

Route::get('/', function () {
    return redirect('/inventory');
});

Route::get('/inventory', [InventoryController::class, 'index'])->name('inventory.index');
Route::get('/inventory/guia', function () {
    return view('workflow-guide');
})->name('inventory.workflow');
Route::post('/inventory/scan', [InventoryController::class, 'procesarInput'])->name('inventory.scan');
Route::post('/inventory/scan/rfid', [InventoryController::class, 'procesarInputRfidApi'])->name('inventory.scan.rfid');
Route::get('/inventory/scan/rfid/ping', [InventoryController::class, 'pingRfid'])->name('inventory.scan.rfid.ping');
Route::get('/inventory/telemetry', [HardwareTelemetryController::class, 'index'])->name('inventory.telemetry.index');
Route::post('/inventory/telemetry/collect', [HardwareTelemetryController::class, 'collect'])->name('inventory.telemetry.collect');
Route::get('/inventory/telemetry/snapshot', [HardwareTelemetryController::class, 'snapshot'])->name('inventory.telemetry.snapshot');
Route::get('/historial', [HistorialController::class, 'index'])->name('historial');

// Registration Routes
Route::get('/inventory/register/{nfc_id}', [InventoryController::class, 'showRegister'])->name('inventory.register');
Route::post('/inventory/register/student', [InventoryController::class, 'storeStudent'])->name('inventory.storeStudent');
Route::post('/inventory/register/camera', [InventoryController::class, 'storeCamera'])->name('inventory.storeCamera');
Route::post('/inventory/register/student/new', [InventoryController::class, 'storeNewStudent'])->name('inventory.storeNewStudent');
Route::post('/inventory/register/camera/new', [InventoryController::class, 'storeNewCamera'])->name('inventory.storeNewCamera');
