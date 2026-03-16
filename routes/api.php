<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\EInvoiceController;

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

// Health check (no auth required)
Route::get('/health', function() {
    return response()->json([
        'status' => 'ok',
        'service' => 'LHDN E-Invoice Proxy',
        'timestamp' => now()->toISOString()
    ]);
});

// Protected routes - require API key
Route::middleware(['api.key'])->group(function () {
    // Document operations
    Route::post('/documents', [EInvoiceController::class, 'create']);
    Route::get('/documents/{document_uid}', [EInvoiceController::class, 'getDoc']);
    Route::get('/documents/{document_uid}/raw', [EInvoiceController::class, 'getDocRaw']);
    Route::get('/documents/search', [EInvoiceController::class, 'searchDoc']);

    // Document state changes
    Route::put('/documents/{document_uid}/cancel', [EInvoiceController::class, 'cancelDoc'])
        ->name('e-invoice.cancel-doc');
    Route::put('/documents/{document_uid}/reject', [EInvoiceController::class, 'rejectDoc']);

    // Submissions
    Route::get('/submissions/{submission_uid}', [EInvoiceController::class, 'getSubmission']);

    // Utilities
    Route::post('/validate/tin', [EInvoiceController::class, 'validateTin']);
    Route::get('/documents/{document_uid}/qr', [EInvoiceController::class, 'generateQR']);
    Route::get('/settings/{code}', [EInvoiceController::class, 'getCodeSetting']);
});
