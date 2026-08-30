<?php

use App\Http\Controllers\Auth\DiscordController;
use App\Http\Controllers\Control\CardCatalogueController;
use App\Http\Controllers\Control\CharacterController;
use App\Http\Controllers\Control\DiscordGuildController;
use App\Http\Controllers\Control\EquipmentCardTypeController;
use App\Http\Controllers\Control\FacilityController;
use App\Http\Controllers\Control\FacilityTypeController;
use App\Http\Controllers\Control\GameController;
use App\Http\Controllers\Control\PhaseController;
use App\Http\Controllers\Control\ProtectionCardHoldingController;
use App\Http\Controllers\Control\ProtectionCardTypeController;
use App\Http\Controllers\Control\TechnologyTypeController;
use App\Http\Controllers\Control\TrackerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FacilityBoardController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware('guest')->group(function () {
    Route::get('auth/discord', [DiscordController::class, 'redirect'])->name('auth.discord');
    Route::get('auth/discord/callback', [DiscordController::class, 'callback'])->name('auth.discord.callback');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    // Players' own view of the Facilities: the public list everyone may see,
    // plus their own Corporation's defences in full.
    Route::get('facilities', FacilityBoardController::class)->name('facilities');

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
            // Destructive, and the only route in the application that is. The
            // request will not let it through without the game's name typed out.
            Route::post('games/{game}/discord/reset', [DiscordGuildController::class, 'reset'])
                ->name('games.discord.reset');

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
            Route::post('games/{game}/facilities/publish-list', [FacilityController::class, 'publishList'])
                ->name('facilities.publish-list');
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
            Route::post('games/{game}/facilities/{facility}/channels', [FacilityController::class, 'provisionChannels'])
                ->name('facilities.channels');

            Route::post('games/{game}/facility-types', [FacilityTypeController::class, 'store'])
                ->name('facility-types.store');
            Route::patch('games/{game}/facility-types/{facilityType}', [FacilityTypeController::class, 'update'])
                ->name('facility-types.update');
            Route::delete('games/{game}/facility-types/{facilityType}', [FacilityTypeController::class, 'destroy'])
                ->name('facility-types.destroy');

            // How many copies of a card a Corporation owns (rulebook 3.3.4).
            // Control's to set: everything that moves it happens at the table.
            Route::patch('games/{game}/protection-card-holdings', [ProtectionCardHoldingController::class, 'update'])
                ->name('protection-card-holdings.update');

            // All three card lists to look at, and the two that are not edited
            // beside the Facilities to change.
            Route::get('games/{game}/cards', [CardCatalogueController::class, 'index'])
                ->name('cards.index');

            // Control adds Equipment during play: a DTC technology invents a
            // bypass card that was never printed.
            Route::post('games/{game}/equipment-cards', [EquipmentCardTypeController::class, 'store'])
                ->name('equipment-cards.store');
            Route::patch('games/{game}/equipment-cards/{equipmentCard}', [EquipmentCardTypeController::class, 'update'])
                ->name('equipment-cards.update');
            Route::delete('games/{game}/equipment-cards/{equipmentCard}', [EquipmentCardTypeController::class, 'destroy'])
                ->name('equipment-cards.destroy');

            // And technologies, because rulebook 3.2.4 has players writing
            // research proposals that Research Control prices on the night.
            Route::post('games/{game}/technologies', [TechnologyTypeController::class, 'store'])
                ->name('technologies.store');
            Route::patch('games/{game}/technologies/{technology}', [TechnologyTypeController::class, 'update'])
                ->name('technologies.update');
            Route::delete('games/{game}/technologies/{technology}', [TechnologyTypeController::class, 'destroy'])
                ->name('technologies.destroy');

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
