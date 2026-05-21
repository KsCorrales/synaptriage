<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TicketController;


Route::inertia('/', 'Welcome')->name('home');
Route::get('/tickets', [TicketController::class, 'index'])->name('tickets.index');
Route::post('/tickets', [TicketController::class, 'store'])->name('tickets.store');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';
