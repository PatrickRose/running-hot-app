<?php

use App\Http\Controllers\AgendaCardController;
use App\Http\Controllers\Auth\CharacterClaimController;
use App\Http\Controllers\Auth\DiscordController;
use App\Http\Controllers\CardListController;
use App\Http\Controllers\Control\CardCatalogueController;
use App\Http\Controllers\Control\CharacterController;
use App\Http\Controllers\Control\ControlMemberController;
use App\Http\Controllers\Control\CouncilController as ControlCouncilController;
use App\Http\Controllers\Control\DiceRollController as ControlDiceRollController;
use App\Http\Controllers\Control\DiscordGuildController;
use App\Http\Controllers\Control\EquipmentCardTypeController;
use App\Http\Controllers\Control\EquipmentHoldingController;
use App\Http\Controllers\Control\FacilityController;
use App\Http\Controllers\Control\FacilityTypeController;
use App\Http\Controllers\Control\GameController;
use App\Http\Controllers\Control\PhaseController;
use App\Http\Controllers\Control\ProtectionCardHoldingController;
use App\Http\Controllers\Control\ProtectionCardTypeController;
use App\Http\Controllers\Control\ResearchCardController;
use App\Http\Controllers\Control\ResearchController;
use App\Http\Controllers\Control\ResearchEquationController;
use App\Http\Controllers\Control\ResearchSessionController;
use App\Http\Controllers\Control\ShopController as ControlShopController;
use App\Http\Controllers\Control\StatsController;
use App\Http\Controllers\Control\StockCertificateController as ControlStockCertificateController;
use App\Http\Controllers\Control\TechnologyHoldingController;
use App\Http\Controllers\Control\TechnologyTypeController;
use App\Http\Controllers\Control\TrackerController;
use App\Http\Controllers\CouncilBallotController;
use App\Http\Controllers\CouncilChairController;
use App\Http\Controllers\CouncilController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DiceRollController;
use App\Http\Controllers\EquipmentController;
use App\Http\Controllers\EquipmentTransferController;
use App\Http\Controllers\FacilityBoardController;
use App\Http\Controllers\FacilityDefenceController;
use App\Http\Controllers\FacilityTechnologyController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ResearchBoardController;
use App\Http\Controllers\ResearchTableController;
use App\Http\Controllers\ResearchTreeController;
use App\Http\Controllers\RunBoardController;
use App\Http\Controllers\RunController;
use App\Http\Controllers\ShopController;
use App\Http\Controllers\StockCertificateController;
use Illuminate\Support\Facades\Route;

/*
 * There is no landing page. Everybody who uses this application signs in — a
 * player to their seats, Control to its panel — so the front door is the login
 * form rather than a page describing the game to somebody already here to play
 * it. The name is kept because logging out, deleting an account and asking for
 * a fresh verification mail all redirect to it.
 */
Route::get('/', HomeController::class)->name('home');

Route::middleware('guest')->group(function () {
    Route::get('auth/discord', [DiscordController::class, 'redirect'])->name('auth.discord');
    Route::get('auth/discord/callback', [DiscordController::class, 'callback'])->name('auth.discord.callback');
});

/*
 * "I signed up to this game with this email address."
 *
 * Deliberately in neither middleware group. A visitor names their address and
 * is sent through the Discord sign in; somebody already signed in - because
 * signing in worked, it just found them nothing - is bound on the spot. Both
 * are people the handle failed, so a `guest` or an `auth` here would shut the
 * door on half of them.
 */
Route::get('claim', [CharacterClaimController::class, 'create'])->name('claim');
Route::post('claim', [CharacterClaimController::class, 'store'])->name('claim.store');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    // Players' own view of the Facilities: the public list everyone may see,
    // plus their own Corporation's defences in full.
    Route::get('facilities', FacilityBoardController::class)->name('facilities');

    // Security arranging their own Facilities. Guarded by the FacilityPolicy
    // rather than by a role middleware, because the question is not "is this a
    // Security player" but "is this Facility theirs".
    // The Credits a Facility is defended with (3.3.5). Security's own decision,
    // so Security's own route - Control keeps its panel.
    Route::post('facilities/{facility}/budget', [FacilityDefenceController::class, 'budget'])
        ->name('facilities.budget');

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

    // Where a technology is stored (3.2.2). Security's, for the reason the
    // stacks are: which building holds what is a decision about what a Run
    // would come away with. The Facility in the path is the destination, which
    // is both what the gesture names and what is authorised.
    Route::patch('facilities/{facility}/technologies/{holding}', [FacilityTechnologyController::class, 'move'])
        ->name('facilities.technologies.move');

    // What a Runner is carrying (rulebook 3.4.1). Names no character: a
    // player sees the hands of whoever they have claimed, and Control sees
    // everybody, so the viewer is the whole of the question.
    Route::get('equipment', EquipmentController::class)->name('equipment');

    // The Protection Card and Equipment lists, as printed (3.3.2, 3.4.1).
    // Everybody's: a catalogue is not what 3.4.2 keeps Secret - which cards
    // stand in which Facility is.
    Route::get('cards', CardListController::class)->name('cards');

    // One player handing a card to another (rulebook 2.1). The other half of
    // "either from the market or from other players" - the market being the
    // shop's own counter. What travels is the card; what was agreed in
    // exchange is settled at the table, as a research point trade is.
    Route::post('equipment/give', EquipmentTransferController::class)
        ->name('equipment.give');

    // A pool of d6s and d8s, rolled on the server and shared with Control. The
    // seat is named in the request because a player may hold two, and Control
    // wants to know which of them was rolling.
    Route::get('dice', [DiceRollController::class, 'index'])->name('dice');
    Route::post('dice', [DiceRollController::class, 'store'])->name('dice.store');

    // A Stock Certificate taken out of a Corporate Facility (3.4.3). Its
    // holder cashes it in once, or hands it on - which is how one is sold.
    Route::post('stock-certificates/{certificate}/cash', [StockCertificateController::class, 'cashIn'])
        ->name('stock-certificates.cash');
    Route::post('stock-certificates/{certificate}/give', [StockCertificateController::class, 'give'])
        ->name('stock-certificates.give');

    // The research sub-game (rulebook 3.2). None of these names a Corporation:
    // a player has exactly one, so the seat they hold decides which, and the
    // CorporationPolicy decides whether they may act or only read.
    Route::get('research', ResearchBoardController::class)->name('research');

    Route::post('research/equations', [ResearchTableController::class, 'play'])
        ->name('research.equations.play');
    // Separate from playing, because the rulebook wants scoring to happen
    // "while other players are taking their turns" - so an equation is played
    // now and paid out whenever its player gets round to the arithmetic.
    Route::post('research/equations/{equation}/score', [ResearchTableController::class, 'score'])
        ->name('research.equations.score');

    Route::post('research/technologies', [ResearchTreeController::class, 'research'])
        ->name('research.technologies.store');
    Route::post('research/deck', [ResearchTreeController::class, 'customiseDeck'])
        ->name('research.deck.store');
    Route::post('research/points', [ResearchTreeController::class, 'transferPoints'])
        ->name('research.points.transfer');
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
    Route::post('runs/{run}/defend', [RunController::class, 'defend'])->name('runs.defend');
    Route::post('runs/{run}/challenge', [RunController::class, 'challenge'])->name('runs.challenge');
    Route::post('runs/{run}/consequences/mark', [RunController::class, 'markConsequence'])
        ->name('runs.consequences.mark');
    Route::post('runs/{run}/consequences', [RunController::class, 'consequence'])
        ->name('runs.consequences.store');
    // What a Runner is taking in, and what they play once inside (3.4.1,
    // 3.4.2). Each is `act` plus a check that the character named is one this
    // player holds, because `act` only asks whether you are on the run - and
    // every Runner on it passes that, so a gangmate's kit would be reachable.
    Route::post('runs/{run}/equipment', [RunController::class, 'equip'])
        ->name('runs.equipment.store');
    Route::post('runs/{run}/equipment/play', [RunController::class, 'playEquipment'])
        ->name('runs.equipment.play');

    // What that card is doing to your skills for the rest of the run. Its own
    // route because a skill is halved on the way into the pool for everybody
    // who is not leading, so it is not the same thing as adding dice to a roll.
    Route::post('runs/{run}/skills', [RunController::class, 'adjustSkills'])
        ->name('runs.skills.update');

    Route::post('runs/{run}/leave', [RunController::class, 'leave'])->name('runs.leave');
    Route::post('runs/{run}/advance', [RunController::class, 'advance'])->name('runs.advance');

    // What the Runners take out of a Facility they got into (3.4.3). One route
    // for all four kinds of access, because it is one act with one choice.
    Route::post('runs/{run}/accesses', [RunController::class, 'access'])->name('runs.accesses.store');

    // What to do with the card an access turned up. Its own route because the
    // rulebook makes it its own step: the card is revealed and then decided on.
    Route::post('runs/{run}/accesses/{access}', [RunController::class, 'resolveAccess'])
        ->name('runs.accesses.resolve');

    // The shop (rulebook 3.3.3, and 2.1 for the Runners' market). One page for
    // both counters, because a user claims characters rather than a side and
    // plenty of people are at both - which of them they are shown is the
    // ShopPresenter's answer.
    Route::get('shop', [ShopController::class, 'index'])->name('shop');

    // Buying one copy. The character is named in the request rather than
    // inferred, because which seat is standing at the counter decides whose
    // Credits pay and whose hand the card lands in - and the ShopListingPolicy
    // is what checks the seat matches the counter, and that the shop is open.
    Route::post('shop/{listing}/buy', [ShopController::class, 'buy'])->name('shop.buy');

    // The Council (rulebook 3.1). A seat is what it takes to read it at all -
    // the controller refuses without one - and who may vote, and who may
    // chair, narrows from there in the CouncilSessionPolicy rather than in a
    // middleware: the Chair is a Corporation that changes every turn.
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

                // Every number in the game, in one place. The trackers move
                // through TrackerService as they always did; the four printed
                // stats on a character sheet are edited nowhere else.
                Route::get('games/{game}/stats', [StatsController::class, 'index'])
                    ->name('stats.index');

                // What players have rolled for Control to read.
                Route::get('games/{game}/dice', [ControlDiceRollController::class, 'index'])
                    ->name('dice.index');

                Route::post('games/{game}/trackers', [TrackerController::class, 'store'])->name('trackers.store');
                Route::post('games/{game}/characters/{character}/remove-tag', [TrackerController::class, 'removeTag'])
                    ->name('characters.remove-tag');
                Route::patch('games/{game}/characters/{character}/stats', [CharacterController::class, 'updateStats'])
                    ->name('characters.stats');

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
                Route::post('games/{game}/protection-card-holdings/give', [ProtectionCardHoldingController::class, 'give'])
                    ->name('protection-card-holdings.give');
                Route::patch('games/{game}/protection-card-holdings', [ProtectionCardHoldingController::class, 'update'])
                    ->name('protection-card-holdings.update');

                // What Equipment each player is carrying (rulebook 3.4.1).
                // Control's to set: the market, a Runner buying from another
                // player (2.1) and a gang splitting a haul all happen at the
                // table. Giving adds copies and setting replaces the count,
                // which is why there are two of them.
                Route::post('games/{game}/equipment-holdings/give', [EquipmentHoldingController::class, 'give'])
                    ->name('equipment-holdings.give');
                Route::patch('games/{game}/equipment-holdings', [EquipmentHoldingController::class, 'update'])
                    ->name('equipment-holdings.update');

                // Stock Certificates (3.4.3): Control hands one to the Runner
                // who chose it from a Corporate Facility, and takes back one
                // handed out by mistake.
                Route::post('games/{game}/stock-certificates', [ControlStockCertificateController::class, 'store'])
                    ->name('stock-certificates.store');
                Route::delete('games/{game}/stock-certificates/{certificate}', [ControlStockCertificateController::class, 'destroy'])
                    ->name('stock-certificates.destroy');

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
                Route::patch('games/{game}/technologies/{technology}/cost', [TechnologyTypeController::class, 'cost'])
                    ->name('technologies.cost');
                Route::delete('games/{game}/technologies/{technology}', [TechnologyTypeController::class, 'destroy'])
                    ->name('technologies.destroy');

                // The research sub-game as Research Control runs it (rulebook
                // 3.2): the table, what each equation paid, the technology
                // cards Corporations hold, and the decks they play from.
                Route::get('games/{game}/research', [ResearchController::class, 'index'])
                    ->name('research.index');

                Route::post('games/{game}/research/session', [ResearchSessionController::class, 'store'])
                    ->name('research.session.store');
                Route::post('games/{game}/research/session/close', [ResearchSessionController::class, 'close'])
                    ->name('research.session.close');
                Route::post('games/{game}/research/session/order', [ResearchSessionController::class, 'randomise'])
                    ->name('research.session.order');
                Route::post('games/{game}/research/session/advance', [ResearchSessionController::class, 'advance'])
                    ->name('research.session.advance');
                Route::post(
                    'games/{game}/research/session/seats/{corporation}',
                    [ResearchSessionController::class, 'seat'],
                )->name('research.session.seat');

                // Scoring is normally the player's. Unscoring is what makes it
                // overridable: the points go back and it can be scored again,
                // both movements in the ledger.
                Route::post(
                    'games/{game}/research/equations/{equation}/score',
                    [ResearchEquationController::class, 'score'],
                )->name('research.equations.score');
                Route::post(
                    'games/{game}/research/equations/{equation}/unscore',
                    [ResearchEquationController::class, 'unscore'],
                )->name('research.equations.unscore');
                Route::post(
                    'games/{game}/research/equations/{equation}/void',
                    [ResearchEquationController::class, 'void'],
                )->name('research.equations.void');

                // Sharing a copy, and whatever a Run brought back (3.2.5,
                // 3.2.6). All of it goes through Research Control at the table,
                // so all of it is Control's here.
                Route::post('games/{game}/technology-holdings', [TechnologyHoldingController::class, 'store'])
                    ->name('technology-holdings.store');
                Route::patch(
                    'games/{game}/technology-holdings/{holding}',
                    [TechnologyHoldingController::class, 'update'],
                )->name('technology-holdings.update');
                Route::delete(
                    'games/{game}/technology-holdings/{holding}',
                    [TechnologyHoldingController::class, 'destroy'],
                )->name('technology-holdings.destroy');

                // Upgrading a card in a deck, which the tree prices nowhere -
                // so it is a custom proposal under 3.2.4.
                Route::patch('games/{game}/research-cards/{card}', [ResearchCardController::class, 'update'])
                    ->name('research-cards.update');

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

                // The shop (rulebook 3.3.3). Control announces what is for
                // sale, so the list, the prices and the stock are Control's -
                // and so is unwinding a sale that should not have happened.
                Route::get('games/{game}/shop', [ControlShopController::class, 'index'])
                    ->name('shop.index');
                Route::post('games/{game}/shop', [ControlShopController::class, 'stock'])
                    ->name('shop.stock');
                Route::delete('games/{game}/shop/{listing}', [ControlShopController::class, 'destroy'])
                    ->name('shop.destroy');
                // Buying for a player who phoned it in, on the same service the
                // players' own route uses - so every rule still applies.
                Route::post('games/{game}/shop/{listing}/buy', [ControlShopController::class, 'buy'])
                    ->name('shop.buy');
                Route::delete('games/{game}/shop/purchases/{purchase}', [ControlShopController::class, 'refund'])
                    ->name('shop.refund');

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
                // Seating somebody who is not a Corporation, which is how HM
                // Government comes to be at the Council at all.
                Route::post('games/{game}/council/seats', [ControlCouncilController::class, 'seat'])
                    ->name('council.seats.store');
                Route::post('games/{game}/council/penalties', [ControlCouncilController::class, 'penalty'])
                    ->name('council.penalties.store');

                Route::post('games/{game}/characters/{character}/discord', [CharacterController::class, 'updateDiscord'])
                    ->name('characters.discord');
                Route::post('games/{game}/characters/{character}/email', [CharacterController::class, 'updateEmail'])
                    ->name('characters.email');
                Route::post('games/{game}/characters/{character}/release', [CharacterController::class, 'release'])
                    ->name('characters.release');
            });
        });
});

require __DIR__.'/settings.php';
