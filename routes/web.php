<?php

use App\Http\Controllers\ClaimController;
use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
    ]);
});

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::post('/claims', [ClaimController::class, 'store'])->name('claims.store');
    Route::get('/claims', [ClaimController::class, 'index'])->name('claims.index');
    Route::get('/claims/create', [ClaimController::class, 'create'])->name('claims.create');
   
    Route::get('/claims/{claim}', [ClaimController::class, 'show'])->name('claims.show');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
