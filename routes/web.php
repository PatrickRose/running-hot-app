<?php

use App\Http\Controllers\Auth\DiscordController;
use App\Http\Controllers\Control\CharacterController;
use App\Http\Controllers\Control\DiscordGuildController;
use App\Http\Controllers\Control\FacilityController;
use App\Http\Controllers\Control\FacilityTypeController;
use App\Http\Controllers\Control\GameController;
use App\Http\Controllers\Control\PhaseController;
use App\Http\Controllers\Control\ProtectionCardTypeController;
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
            Route::post('games/{game}/webhook', [GameController::class, 'updateWebhook'])->name('games.webhook');

            Route::post('games/{game}/discord', [DiscordGuildController::class, 'update'])->name('games.discord');
            Route::get('games/{game}/discord/connect', [DiscordGuildController::class, 'connect'])
                ->name('games.discord.connect');
            // Fixed URI, so it carries no game: the pending game is held in the
            // session and matched against Discord's state parameter.
            Route::get('discord/callback', [DiscordGuildController::class, 'callback'])
                ->name('games.discord.callback');
            Route::post('games/{game}/discord/provision', [DiscordGuildController::class, 'provision'])
                ->name('games.discord.provision');
            Route::post('games/{game}/discord/sync-roles', [DiscordGuildController::class, 'syncRoles'])
                ->name('games.discord.sync-roles');

            Route::post('games/{game}/phase/start', [PhaseController::class, 'start'])->name('phase.start');
            Route::post('games/{game}/phase/advance', [PhaseController::class, 'advance'])->name('phase.advance');
            Route::post('games/{game}/phase/pause', [PhaseController::class, 'pause'])->name('phase.pause');
            Route::post('games/{game}/phase/resume', [PhaseController::class, 'resume'])->name('phase.resume');
            Route::post('games/{game}/phase/extend', [PhaseController::class, 'extend'])->name('phase.extend');

            Route::post('games/{game}/trackers', [TrackerController::class, 'store'])->name('trackers.store');
            Route::post('games/{game}/characters/{character}/remove-tag', [TrackerController::class, 'removeTag'])
                ->name('characters.remove-tag');

            // Facility Defence (rulebook 3.3). Facilities and their Protection
            // Card stacks, plus the catalogues both are built from.
            Route::get('games/{game}/facilities', [FacilityController::class, 'index'])
                ->name('facilities.index');
            Route::post('games/{game}/facilities', [FacilityController::class, 'store'])
                ->name('facilities.store');
            Route::patch('games/{game}/facilities/{facility}', [FacilityController::class, 'update'])
                ->name('facilities.update');
            Route::delete('games/{game}/facilities/{facility}', [FacilityController::class, 'destroy'])
                ->name('facilities.destroy');

            Route::post('games/{game}/facilities/{facility}/cards', [FacilityController::class, 'installCard'])
                ->name('facilities.cards.install');
            Route::post('games/{game}/facilities/{facility}/cards/order', [FacilityController::class, 'reorderCards'])
                ->name('facilities.cards.reorder');
            Route::delete('games/{game}/facilities/{facility}/cards/{card}', [FacilityController::class, 'removeCard'])
                ->name('facilities.cards.remove');

            Route::post('games/{game}/facilities/{facility}/security', [FacilityController::class, 'updateSecurity'])
                ->name('facilities.security');

            Route::post('games/{game}/facility-types', [FacilityTypeController::class, 'store'])
                ->name('facility-types.store');
            Route::patch('games/{game}/facility-types/{facilityType}', [FacilityTypeController::class, 'update'])
                ->name('facility-types.update');
            Route::delete('games/{game}/facility-types/{facilityType}', [FacilityTypeController::class, 'destroy'])
                ->name('facility-types.destroy');

            Route::post('games/{game}/protection-cards', [ProtectionCardTypeController::class, 'store'])
                ->name('protection-cards.store');
            Route::patch('games/{game}/protection-cards/{cardType}', [ProtectionCardTypeController::class, 'update'])
                ->name('protection-cards.update');
            Route::delete('games/{game}/protection-cards/{cardType}', [ProtectionCardTypeController::class, 'destroy'])
                ->name('protection-cards.destroy');

            Route::post('games/{game}/characters/{character}/discord', [CharacterController::class, 'updateDiscord'])
                ->name('characters.discord');
            Route::post('games/{game}/characters/{character}/release', [CharacterController::class, 'release'])
                ->name('characters.release');
        });
});

require __DIR__.'/settings.php';
