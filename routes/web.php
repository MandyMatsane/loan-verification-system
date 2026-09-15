<?php

use App\Http\Controllers\Admin\ApplicationController as AdminApplicationController;
use App\Http\Controllers\LoanApplicationController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    //loan application routes
    Route::get('/applications/create', [LoanApplicationController::class, 'create'])->name('applications.create');
    Route::post('/applications', [LoanApplicationController::class, 'store'])->name('applications.store');
    Route::get('/applications/{application}/confirmation', [LoanApplicationController::class, 'confirmation'])->name('applications.confirmation');
    Route::get('/applications/{application}', [LoanApplicationController::class, 'show'])->name('applications.show');
    Route::post('/applications/{application}/documents', [LoanApplicationController::class, 'uploadDocument'])->name('applications.documents.store');

    // Admin routes
    Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/applications', [AdminApplicationController::class, 'index'])->name('applications.index');
    Route::get('/applications/{application}', [AdminApplicationController::class, 'show'])->name('applications.show');
});
});

require __DIR__.'/auth.php';
