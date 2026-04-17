<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RedisAdminController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/dashboard', [RedisAdminController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

// Redis API routes
Route::middleware(['auth', 'verified'])
    ->prefix('api/redis')
    ->name('redis.')
    ->controller(RedisAdminController::class)
    ->group(function () {
        Route::get('/key', 'show')->name('key.show');
        Route::post('/key/delete', 'destroy')->name('key.delete');
        Route::post('/key/rename', 'rename')->name('key.rename');
        Route::post('/key/ttl', 'updateTtl')->name('key.ttl');
        Route::post('/key/value/update', 'updateValue')->name('key.value.update');
        Route::post('/key/value/delete', 'deleteValue')->name('key.value.delete');
        Route::post('/key/bulk-delete', 'bulkDelete')->name('key.bulk-delete');
        Route::post('/db/flush', 'flushDb')->name('db.flush');
    });

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__ . '/auth.php';
