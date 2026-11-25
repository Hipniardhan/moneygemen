<?php

use App\Http\Controllers\AppController;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\Register;
use App\Livewire\Categories;
use App\Livewire\Dashboard;
use App\Livewire\Profile;
use App\Livewire\Transactions;
use Illuminate\Support\Facades\Route;

Route::view('/offline', 'offline')->name('offline');

Route::middleware('guest')->group(function () {
    Route::get('/masuk', Login::class)->name('login');
    Route::get('/daftar', Register::class)->name('register');
    Route::get('/demo', [AppController::class, 'demo'])->middleware('throttle:10,10')->name('demo');
});

Route::middleware('auth')->group(function () {
    Route::get('/', Dashboard::class)->name('dashboard');
    Route::get('/transaksi', Transactions::class)->name('transactions');
    Route::get('/kategori', Categories::class)->name('categories');
    Route::get('/profil', Profile::class)->name('profile');
    Route::redirect('/pasangan', '/profil');
    Route::get('/laporan', \App\Livewire\Report::class)->name('report');
    Route::get('/ekspor.csv', [\App\Http\Controllers\ExportController::class, 'csv'])->middleware('throttle:20,1')->name('export');

    Route::post('/keluar', [AppController::class, 'logout'])->name('logout');
    Route::post('/push/subscribe', [AppController::class, 'subscribe'])->middleware('throttle:20,1');
    Route::post('/push/unsubscribe', [AppController::class, 'unsubscribe'])->middleware('throttle:20,1');
});
