<?php

use App\Http\Controllers\ImageController;
use Illuminate\Support\Facades\Route;

Route::get('/images', [ImageController::class, 'index'])->name('images.index');
Route::post('/images', [ImageController::class, 'store'])->name('images.store')->middleware('throttle:uploads');
Route::get('/images/{image}', [ImageController::class, 'show'])->name('images.show');
Route::get('/images/{image}/download', [ImageController::class, 'download'])->name('images.download');
Route::delete('/images/{image}', [ImageController::class, 'destroy'])->name('images.destroy')->middleware('throttle:deletes');
