<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\RapprochementController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::prefix('export')->name('export.')->group(function () {
        Route::get('/', [ExportController::class, 'page'])->name('page');
        Route::get('installations.xlsx', [ExportController::class, 'installationsXlsx'])->name('installations');
    });

    Route::prefix('rapprochements')->name('rapprochements.')->group(function () {
        Route::get('/', [RapprochementController::class, 'index'])->name('index');
        Route::post('valider-tout', [RapprochementController::class, 'validerTout'])->name('valider-tout');
        Route::post('{rapprochement}/valider', [RapprochementController::class, 'valider'])->name('valider');
        Route::post('{rapprochement}/rejeter', [RapprochementController::class, 'rejeter'])->name('rejeter');
    });
});

require __DIR__.'/settings.php';
