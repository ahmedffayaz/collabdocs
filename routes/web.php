<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DocumentController;

Route::view('/', 'app')->name('home');

Route::prefix('api')->group(function () {
    Route::get('/documents', [DocumentController::class, 'index'])->name('api.documents.index');
    Route::post('/active-user', [DocumentController::class, 'switchUser'])->name('api.active-user');
    Route::post('/documents', [DocumentController::class, 'store'])->name('api.documents.store');
    Route::post('/documents/import', [DocumentController::class, 'import'])->name('api.documents.import');
    Route::get('/documents/{document}', [DocumentController::class, 'show'])->name('api.documents.show');
    Route::put('/documents/{document}', [DocumentController::class, 'update'])->name('api.documents.update');
    Route::delete('/documents/{document}', [DocumentController::class, 'destroy'])->name('api.documents.destroy');
    Route::post('/documents/{document}/share', [DocumentController::class, 'share'])->name('api.documents.share');
});
