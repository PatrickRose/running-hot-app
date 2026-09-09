<?php

use App\Http\Controllers\AgendaCardController;
use App\Http\Controllers\Auth\DiscordController;
use App\Http\Controllers\Control\CardCatalogueController;
use App\Http\Controllers\Control\CharacterController;
use App\Http\Controllers\Control\ControlMemberController;
use App\Http\Controllers\Control\CouncilController as ControlCouncilController;
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
use App\Http\Controllers\CouncilBallotController;
use App\Http\Controllers\CouncilChairController;
use App\Http\Controllers\CouncilController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FacilityBoardController;
use App\Http\Controllers\FacilityDefenceController;
use App\Http\Controllers\RunBoardController;
use App\Http\Controllers\RunController;
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

    // Security arranging their own Facilities. Guarded by the FacilityPolicy
    // rather than by a role middleware, because the question is not "is this a
    // Security player" but "is this Facility theirs".
    Route::post('facilities/{facility}/cards', [FacilityDefenceController::class, 'install'])
        ->name('facilities.cards.install');
    Route::post('facilities/{facility}/cards/order', [FacilityDefenceController::class, 'reorder'])
        ->name('facilities.cards.reorder');
    // A GET, because asking what an arrangement would cost is a question
    // rather than a change - and a question needs no CSRF token, so the board
    // can ask it with a plain fetch as the cards move.
    Route::get('facilities/{facility}/cards/order/quote', [FacilityDefenceController::class, 'quote'])
        ->name('facilities.cards.quote');
    Route::delete('facilities/{facility}/cards/{card}', [FacilityDefenceController::class, 'remove'])
        ->name('facilities.cards.remove');

    // Runs (rulebook 3.4). One page for both sides of the Facility game,
    // because plenty of people are on both at once - and what each of them may
    // see is the RunPresenter's answer, since a run keeps two secrets: which
    // Facility a group named, and how deep the stack is.
    Route::get('runs', RunBoardController::class)->name('runs');

    // Putting in for one. Guarded by the RunPolicy rather than a role
    // middleware: the question is whether this player holds a character who
    // could go on a run, which a Freelancer does as much as a Runner.
    Route::post('runs', [RunController::class, 'store'])->name('runs.store');

    // The queue at a Facility, which is Control's to settle (3.4.1) - a group
    // that could order it could put itself first.
    Route::post('facilities/{facility}/runs/order', [RunController::class, 'order'])
        ->name('runs.order');

    // One route per act of the loop, each authorised as its own thing: the
    // Leader rolls and moves the group on, any Runner may walk away, and
    // Security works the cards. Control reaches all of them through the same
    // routes, because a run must not stall on somebody being at their laptop.
    Route::post('runs/{run}/begin', [RunController::class, 'begin'])->name('runs.begin');
    Route::post('runs/{run}/activate', [RunController::class, 'activate'])->name('runs.activate');
    Route::post('runs/{run}/boost', [RunController::class, 'boost'])->name('runs.boost');
    Route::post('runs/{run}/charge', [RunController::class, 'charge'])->name('runs.charge');
    Route::post('runs/{run}/challenge', [RunController::class, 'challenge'])->name('runs.challenge');
    Route::post('runs/{run}/consequences', [RunController::class, 'consequence'])
        ->name('runs.consequences.store');
    Route::post('runs/{run}/alerts', [RunController::class, 'triggerWithAlerts'])
        ->name('runs.alerts.trigger');
    Route::post('runs/{run}/ignore-end', [RunController::class, 'ignoreEnd'])->name('runs.ignore-end');
    Route::post('runs/{run}/leave', [RunController::class, 'leave'])->name('runs.leave');
    Route::post('runs/{run}/advance', [RunController::class, 'advance'])->name('runs.advance');

    // The Council (rulebook 3.1). Everyone playing may read it, because the
    // agenda is read out and any player may write a custom one. Who may vote,
    // and who may chair, is the CouncilSessionPolicy's answer rather than a
    // middleware's - the Chair is a Corporation that changes every turn.
    Route::get('council', CouncilController::class)->name('council');

    Route::post('council/items/{item}/ballots', [CouncilBallotController::class, 'store'])
        ->name('council.ballots.store');
    // The Chair hands a slip back: every vote already in when a vote is
    // declared secret, and the only way a CEO gets to change one.
    Route::delete('council/ballots/{ballot}', [CouncilBallotController::class, 'destroy'])
        ->name('council.ballots.return');

    // The Chair's own powers over the agenda.
    Route::post('council/{session}/hand', [CouncilChairController::class, 'keep'])
        ->name('council.hand.keep');
    Route::post('council/{session}/promote', [CouncilChairController::class, 'promote'])
        ->name('council.promote');
    Route::post('council/{session}/rulings', [CouncilChairController::class, 'rule'])
        ->name('council.rulings.store');
    Route::post('council/{session}/agenda-cards/{card}/amendments', [CouncilChairController::class, 'amend'])
        ->name('council.amendments.store');
    Route::post('council/items/{item}/secret', [CouncilChairController::class, 'secret'])
        ->name('council.items.secret');
    Route::post('council/items/{item}/resolve', [CouncilChairController::class, 'resolve'])
        ->name('council.items.resolve');

    // Custom agendas: written by a player, annotated by Control, and submitted
    // to the Chair by the player once they agree (3.1.3).
    Route::post('council/agenda-cards', [AgendaCardController::class, 'store'])
        ->name('council.agenda-cards.store');
    Route::patch('council/agenda-cards/{card}', [AgendaCardController::class, 'update'])
        ->name('council.agenda-cards.update');
    Route::post('council/agenda-cards/{card}/to-control', [AgendaCardController::class, 'submitToControl'])
        ->name('council.agenda-cards.to-control');
    Route::post('council/agenda-cards/{card}/to-chair', [AgendaCardController::class, 'submitToChair'])
        ->name('council.agenda-cards.to-chair');

    Route::middleware('can:control')
        ->prefix('control')
        ->name('control.')
        ->group(function () {
            Route::get('games', [GameController::class, 'index'])->name('games.index');
            Route::post('games', [GameController::class, 'store'])->name('games.store');
            // Fixed URI, so it carries no game: the pending game is held in the
            // session and matched against Discord's state parameter.
            Route::get('discord/callback', [DiscordGuildController::class, 'callback'])
                ->name('games.discord.callback');

            // Everything below names a game, so it is gated on being Control of
            // that game rather than of merely some game.
            Route::middleware('can:control-game,game')->group(function () {
                Route::get('games/{game}', [GameController::class, 'show'])->name('games.show');

                Route::post('games/{game}/finish', [GameController::class, 'finish'])->name('games.finish');
                Route::post('games/{game}/webhook', [GameController::class, 'updateWebhook'])->name('games.webhook');

                Route::post('games/{game}/discord', [DiscordGuildController::class, 'update'])->name('games.discord');
                Route::get('games/{game}/discord/connect', [DiscordGuildController::class, 'connect'])
                    ->name('games.discord.connect');
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

                // Who is running this game. A Discord handle each, claimed at
                // sign in exactly as a character is.
                Route::post('games/{game}/control-members', [ControlMemberController::class, 'store'])
                    ->name('control-members.store');
                Route::delete('games/{game}/control-members/{controlMember}', [ControlMemberController::class, 'destroy'])
                    ->name('control-members.destroy');

                // The Council (rulebook 3.1). Control's half of it: the deck
                // and the draw, the remarks on a custom agenda, the sign-off
                // on an amendment, and the cost of an empty seat. The Chair
                // runs the rest through its own routes, which Control may also
                // reach - the policy lets Control through everywhere.
                Route::get('games/{game}/council', [ControlCouncilController::class, 'index'])
                    ->name('council.index');

                Route::post('games/{game}/council/agenda-cards', [ControlCouncilController::class, 'storeCard'])
                    ->name('council.agenda-cards.store');
                Route::patch('games/{game}/council/agenda-cards/{card}', [ControlCouncilController::class, 'updateCard'])
                    ->name('council.agenda-cards.update');
                Route::delete('games/{game}/council/agenda-cards/{card}', [ControlCouncilController::class, 'destroyCard'])
                    ->name('council.agenda-cards.destroy');
                Route::post('games/{game}/council/agenda-cards/{card}/annotate', [ControlCouncilController::class, 'annotate'])
                    ->name('council.agenda-cards.annotate');

                Route::post('games/{game}/council/hand', [ControlCouncilController::class, 'hand'])
                    ->name('council.hand');
                Route::post('games/{game}/council/chair', [ControlCouncilController::class, 'chair'])
                    ->name('council.chair');
                Route::post('games/{game}/council/rotation', [ControlCouncilController::class, 'rotation'])
                    ->name('council.rotation');
                Route::post('games/{game}/council/recess', [ControlCouncilController::class, 'recess'])
                    ->name('council.recess');
                Route::post('games/{game}/council/amendments/{resolution}', [ControlCouncilController::class, 'amendment'])
                    ->name('council.amendments.update');
                Route::post('games/{game}/council/attendance', [ControlCouncilController::class, 'attendance'])
                    ->name('council.attendance');
                Route::post('games/{game}/council/penalties', [ControlCouncilController::class, 'penalty'])
                    ->name('council.penalties.store');

                Route::post('games/{game}/characters/{character}/discord', [CharacterController::class, 'updateDiscord'])
                    ->name('characters.discord');
                Route::post('games/{game}/characters/{character}/release', [CharacterController::class, 'release'])
                    ->name('characters.release');
            });
        });
});

require __DIR__.'/settings.php';
