<?php

use App\Http\Controllers\Auth\DiscordController;
use App\Http\Controllers\Control\GameController;
use App\Http\Controllers\Control\PhaseController;
use App\Http\Controllers\Control\TrackerController;
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware('guest')->group(function () {
    Route::get('auth/discord', [DiscordController::class, 'redirect'])->name('auth.discord');
    Route::get('auth/discord/callback', [DiscordController::class, 'callback'])->name('auth.discord.callback');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::middleware('can:control')
        ->prefix('control')
        ->name('control.')
        ->group(function () {
            Route::get('games', [GameController::class, 'index'])->name('games.index');
            Route::post('games', [GameController::class, 'store'])->name('games.store');
            Route::get('games/{game}', [GameController::class, 'show'])->name('games.show');
            Route::post('games/{game}/finish', [GameController::class, 'finish'])->name('games.finish');

            Route::post('games/{game}/phase/start', [PhaseController::class, 'start'])->name('phase.start');
            Route::post('games/{game}/phase/advance', [PhaseController::class, 'advance'])->name('phase.advance');
            Route::post('games/{game}/phase/pause', [PhaseController::class, 'pause'])->name('phase.pause');
            Route::post('games/{game}/phase/resume', [PhaseController::class, 'resume'])->name('phase.resume');
            Route::post('games/{game}/phase/extend', [PhaseController::class, 'extend'])->name('phase.extend');

            Route::post('games/{game}/trackers', [TrackerController::class, 'store'])->name('trackers.store');
            Route::post('games/{game}/characters/{character}/remove-tag', [TrackerController::class, 'removeTag'])
                ->name('characters.remove-tag');
        });
});

require __DIR__.'/settings.php';
