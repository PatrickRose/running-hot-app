<?php

namespace App\Services;

use App\Actions\SeedResearchDecks;
use App\Enums\EquationSide;
use App\Enums\ResearchCardRestriction;
use App\Enums\ResearchEquationStatus;
use App\Enums\ResearchSuit;
use App\Enums\ResearchZone;
use App\Models\Corporation;
use App\Models\Facility;
use App\Models\Game;
use App\Models\ResearchCard;
use App\Models\ResearchEquation;
use App\Models\ResearchSeat;
use App\Models\ResearchSession;
use App\Models\TechnologyType;
use App\Models\Turn;
use App\Models\User;
use App\Support\Equation;
use App\Support\EquationCard;
use App\Support\FacilityTypeBlueprint;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The research card game (rulebook 3.2.1), and the deck it is played from
 * (3.2.3).
 *
 * This owns every write to research_cards: the shuffle, the deal, spending a
 * card into an equation and drawing its replacement. Nothing else may move a
 * card between zones, for the reason nothing else may renumber a Protection
 * Card stack - the invariants are easy to state and easy to break from a
 * controller.
 *
 * Two decisions here are worth knowing about before changing anything.
 *
 * Playing and scoring are separate acts. The rulebook says scoring "can and
 * should be done while other players are taking their turns", so play() spends
 * the cards, refills the hand and the pool and passes the turn on, and leaves
 * the equation Pending. score() pays it out whenever its player gets round to
 * it, which may be several turns later. Nothing at the table waits on somebody
 * doing arithmetic.
 *
 * The arithmetic itself is not here. It is in App\Support\Equation, which is
 * pure and is tested against the rulebook's own worked examples - the same
 * reasoning that keeps the Protection Card move cost in one method. This service
 * decides who may play and what happens to the cards; that class decides what an
 * equation is worth.
 */
class ResearchTableService
{
    public function __construct(
        private readonly TrackerService $trackers,
        private readonly SeedResearchDecks $decks,
    ) {}

    /**
     * Cards a Corporation holds between turns (rulebook 3.2.1).
     */
    public function handSize(): int
    {
        return max(0, (int) config('running_hot.research.hand_size', 5));
    }

    /**
     * Cards face up in the shared pool.
     */
    public function poolSize(): int
    {
        return max(0, (int) config('running_hot.research.pool_size', 6));
    }

    /**
     * The sitting currently in progress, if there is one.
     */
    public function currentSession(Game $game): ?ResearchSession
    {
        /** @var ResearchSession|null */
        return $game->researchSessions()
            ->whereNull('closed_at')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Deal a new sitting of the research game (rulebook 3.2.1).
     *
     * Every card is gathered back into its deck and shuffled first. The rulebook
     * has each Action phase begin with "Each player draws 5 cards from their
     * private research deck, and 6 cards are dealt out to a public pool", and it
     * has a deck that runs out ending that player's game - so a deck that was
     * never gathered would leave a Corporation out for the rest of the evening
     * rather than out for the rest of the phase. The rulebook does not say to
     * gather; it is the reading that lets the game be played more than once.
     *
     * Any session still open is closed, because there is one research table.
     */
    public function openSession(Game $game, ?Turn $turn = null): ResearchSession
    {
        return DB::transaction(function () use ($game, $turn): ResearchSession {
            $this->currentSession($game)?->forceFill(['closed_at' => Carbon::now()])->save();

            // A Corporation Control added by hand has no deck yet, and a game
            // built without the default roster has none at all.
            $this->decks->handle($game);

            $this->gather($game);

            $session = $game->researchSessions()->create([
                'turn_id' => $turn->id ?? $game->currentTurn()?->id,
                'opened_at' => Carbon::now(),
            ]);

            $this->seat($session, $game);
            $this->deal($session);

            return $session->refresh();
        });
    }

    /**
     * Close the table. "The phase end is called" is one of the two ways the
     * research game ends.
     */
    public function closeSession(ResearchSession $session): ResearchSession
    {
        $session->forceFill([
            'closed_at' => Carbon::now(),
            'current_order' => null,
        ])->save();

        return $session;
    }

    /**
     * Draw the turn order again (rulebook 3.2.1).
     *
     * "A turn order will be decided by Research Control randomly", with a
     * footnote inviting players to tell Research Control about effects that
     * change it - so redrawing is Control's, and it can happen mid-session.
     * Seats that have left keep their place in the order and stay out.
     */
    public function randomiseOrder(ResearchSession $session): ResearchSession
    {
        return DB::transaction(function () use ($session): ResearchSession {
            $seats = $session->seats()->get()->shuffle()->values();

            // Two passes, because the (session, order) unique index will not
            // let two seats share a number even for the moment a swap takes.
            // Parked above every number in use rather than below zero, since the
            // column is unsigned.
            $parked = (int) $session->seats()->max('order') + 1;

            foreach ($seats as $offset => $seat) {
                $seat->forceFill(['order' => $parked + $offset])->save();
            }

            foreach ($seats as $offset => $seat) {
                $seat->forceFill(['order' => $offset + 1])->save();
            }

            return $this->setTurnToFirstPlayer($session);
        });
    }

    /**
     * Pass the turn to the next Corporation still playing.
     *
     * Returns the seat it landed on, or null once everybody has left - which is
     * the other way the research game ends.
     */
    public function advanceTurn(ResearchSession $session): ?ResearchSeat
    {
        $playing = $session->seats()->whereNull('left_at')->orderBy('order')->get();

        if ($playing->isEmpty()) {
            $session->forceFill(['current_order' => null])->save();

            return null;
        }

        $current = $session->current_order;

        /** @var ResearchSeat $next */
        $next = $playing->first(fn (ResearchSeat $seat): bool => $current === null || $seat->order > $current)
            ?? $playing->first();

        $session->forceFill(['current_order' => $next->order])->save();

        return $next;
    }

    /**
     * Take a Corporation out of this sitting (rulebook 3.2.1).
     *
     * Both ways out come through here: choosing to leave the table, and running
     * out of deck when drawing back up. The reason is recorded because they read
     * differently to Control watching the table - one is a player walking off to
     * talk to somebody, the other is a Corporation that has played itself out.
     */
    public function leave(
        ResearchSession $session,
        Corporation $corporation,
        ?string $reason = null,
    ): ResearchSeat {
        return DB::transaction(function () use ($session, $corporation, $reason): ResearchSeat {
            $seat = $this->seatFor($session, $corporation);

            if ($seat->isPlaying()) {
                $seat->forceFill([
                    'left_at' => Carbon::now(),
                    'left_reason' => $reason,
                ])->save();
            }

            // Only move the turn on if it was theirs; leaving out of turn must
            // not skip whoever is actually playing.
            if ($session->current_order === $seat->order) {
                $this->advanceTurn($session);
            }

            return $seat;
        });
    }

    /**
     * Bring a Corporation back to the table, seating it if it was never there.
     *
     * Control's: a player who left to talk to somebody comes back, and 3.2.1
     * makes leaving a choice rather than a forfeit.
     */
    public function rejoin(ResearchSession $session, Corporation $corporation): ResearchSeat
    {
        return DB::transaction(function () use ($session, $corporation): ResearchSeat {
            $seat = $session->seats()->where('corporation_id', $corporation->id)->first();

            if ($seat === null) {
                $seat = $session->seats()->create([
                    'corporation_id' => $corporation->id,
                    'order' => (int) $session->seats()->max('order') + 1,
                ]);
            }

            $seat->forceFill(['left_at' => null, 'left_reason' => null])->save();

            $this->drawUp($session, $corporation);

            if ($session->current_order === null) {
                $this->setTurnToFirstPlayer($session);
            }

            return $seat;
        });
    }

    /**
     * Play an equation (rulebook 3.2.1).
     *
     * The cards are spent, the hand and the pool draw back up, and the turn
     * passes. The equation itself is left Pending: what it is worth is the
     * player's arithmetic to do, and the rulebook asks for it to happen while
     * everybody else carries on.
     *
     * @param  array<int, int>  $leftCardIds
     * @param  array<int, int>  $rightCardIds
     * @param  bool  $enforceTurn  false for Control, who plays a hand on behalf
     *                             of a player who cannot reach a screen
     */
    public function play(
        ResearchSession $session,
        Corporation $corporation,
        array $leftCardIds,
        array $rightCardIds,
        bool $enforceTurn = true,
    ): ResearchEquation {
        return DB::transaction(function () use (
            $session,
            $corporation,
            $leftCardIds,
            $rightCardIds,
            $enforceTurn,
        ): ResearchEquation {
            if (! $session->isOpen()) {
                throw ValidationException::withMessages([
                    'equation' => 'The research table has closed.',
                ]);
            }

            $seat = $this->seatFor($session, $corporation);

            if (! $seat->isPlaying()) {
                throw ValidationException::withMessages([
                    'equation' => $corporation->name.' has left the research table.',
                ]);
            }

            if ($enforceTurn && ! $session->isTurnOf($corporation)) {
                throw ValidationException::withMessages([
                    'equation' => 'It is not '.$corporation->name.'\'s turn at the research table.',
                ]);
            }

            $left = $this->resolveCards($corporation, $leftCardIds, $rightCardIds);
            $right = $this->resolveCards($corporation, $rightCardIds, $leftCardIds);

            $equation = new Equation(
                array_map(
                    fn (ResearchCard $card): EquationCard => $card->toEquationCard(! $card->isPublic()),
                    $left,
                ),
                array_map(
                    fn (ResearchCard $card): EquationCard => $card->toEquationCard(! $card->isPublic()),
                    $right,
                ),
            );

            $equation->validate();

            $record = $session->equations()->create([
                'game_id' => $session->game_id,
                'turn_id' => $session->turn_id,
                'corporation_id' => $corporation->id,
                'status' => ResearchEquationStatus::Pending,
                'left_cards' => $this->snapshot($left),
                'right_cards' => $this->snapshot($right),
                'cards_per_side' => $equation->cardsPerSide(),
                'left_sum' => $equation->sumFor(EquationSide::Left),
                'right_sum' => $equation->sumFor(EquationSide::Right),
                'balanced' => $equation->isBalanced(),
                'bonus' => $equation->balancedBonus(),
            ]);

            foreach ([...$left, ...$right] as $card) {
                $card->forceFill(['zone' => ResearchZone::Spent, 'position' => 0])->save();
            }

            // "You draw cards from your deck to replace any used from your
            // hand", and running dry while doing so ends this Corporation's
            // game for the phase.
            if (! $this->drawUp($session, $corporation)) {
                $this->leave($session, $corporation, ResearchSeat::REASON_DECK_EMPTY);
            } elseif ($session->current_order === $seat->order) {
                $this->advanceTurn($session);
            }

            // "For each card from the public pool that was used, draw a card
            // from the public deck and place into the pool."
            $this->refillPool($session->game);

            return $record;
        });
    }

    /**
     * Take the points an equation is worth (rulebook 3.2.1).
     *
     * Two payments, and the rules differ. The equation pays one side's total in
     * one of that side's suits, so the player chooses a side. A balanced
     * equation pays a bonus on top, which may be split across any suits the
     * equation used - hence the allocation rather than a second suit.
     *
     * @param  array<string, int>  $bonusAllocation  points by suit value
     */
    public function score(
        ResearchEquation $equation,
        EquationSide $side,
        ResearchSuit $suit,
        array $bonusAllocation = [],
        ?User $actor = null,
    ): ResearchEquation {
        return DB::transaction(function () use ($equation, $side, $suit, $bonusAllocation, $actor): ResearchEquation {
            if (! $equation->isPending()) {
                throw ValidationException::withMessages([
                    'equation' => 'That equation has already been '.$equation->status->label().'.',
                ]);
            }

            $awards = $equation->toEquation()->award($side, $suit, $bonusAllocation);

            $this->payAwards($equation, $awards, $actor, 'Research equation');

            $equation->forceFill([
                'status' => ResearchEquationStatus::Scored,
                'scored_side' => $side,
                'scored_suit' => $suit,
                'awards' => $awards,
                'scored_by_id' => $actor?->id,
                'scored_at' => Carbon::now(),
            ])->save();

            return $equation;
        });
    }

    /**
     * Take a score back off, leaving the equation waiting to be scored again.
     *
     * Control's, and the reason score() can be overridden at all: a player who
     * took their points in the wrong suit is corrected by unscoring and scoring
     * again. Both movements are in the ledger, which is the point - the pile of
     * tokens on the table has to be reconcilable with what the application says.
     */
    public function unscore(ResearchEquation $equation, ?User $actor = null): ResearchEquation
    {
        return DB::transaction(function () use ($equation, $actor): ResearchEquation {
            if ($equation->status !== ResearchEquationStatus::Scored) {
                throw ValidationException::withMessages([
                    'equation' => 'That equation has not been scored.',
                ]);
            }

            $this->payAwards(
                $equation,
                array_map(fn (int $points): int => -$points, $equation->awards ?? []),
                $actor,
                'Research equation score taken back',
            );

            $equation->forceFill([
                'status' => ResearchEquationStatus::Pending,
                'scored_side' => null,
                'scored_suit' => null,
                'awards' => null,
                'scored_by_id' => null,
                'scored_at' => null,
            ])->save();

            return $equation;
        });
    }

    /**
     * Strike an equation off, taking any score back with it.
     *
     * Marked rather than deleted: the cards it spent are already gone, and a
     * table that quietly loses a play is a table nobody can audit.
     */
    public function void(ResearchEquation $equation, ?string $reason = null, ?User $actor = null): ResearchEquation
    {
        return DB::transaction(function () use ($equation, $reason, $actor): ResearchEquation {
            if ($equation->status === ResearchEquationStatus::Scored) {
                $this->unscore($equation, $actor);
            }

            $equation->forceFill([
                'status' => ResearchEquationStatus::Voided,
                'notes' => $reason,
            ])->save();

            return $equation;
        });
    }

    /**
     * Buy a card into a Corporation's deck (rulebook 3.2.3).
     *
     * "The costs for this are denoted in your tech tree, and should be handled
     * as if you are researching any other technology" - so the price comes off
     * the technology row, and the six of them that price a deck card do not
     * price like anything else on the tree: the suits are the player's choice.
     * That is what $suits is, one suit per amount the row asks for, all
     * different.
     *
     * The card takes the suit of the first amount, which is the row's "in that
     * suit" and "in the first suit" - unless the row grants a wild, which has
     * none.
     *
     * @param  array<int, ResearchSuit>  $suits  a suit per amount, in order
     */
    public function customiseDeck(
        Corporation $corporation,
        TechnologyType $technology,
        array $suits,
        int $value,
        ?User $actor = null,
    ): ResearchCard {
        $grant = $technology->deckGrant();

        if ($grant === null) {
            throw ValidationException::withMessages([
                'technology_type_id' => $technology->name.' does not add a card to your deck.',
            ]);
        }

        if ($technology->game_id !== $corporation->game_id) {
            throw ValidationException::withMessages([
                'technology_type_id' => 'That technology belongs to a different game.',
            ]);
        }

        if ($technology->corporation_id !== null && $technology->corporation_id !== $corporation->id) {
            throw ValidationException::withMessages([
                'technology_type_id' => $technology->name.' is not on '.$corporation->name.'\'s tree.',
            ]);
        }

        if ($value < $grant['value_min'] || $value > $grant['value_max']) {
            throw ValidationException::withMessages([
                'value' => sprintf(
                    'That card\'s value has to be between %d and %d.',
                    $grant['value_min'],
                    $grant['value_max'],
                ),
            ]);
        }

        $required = $grant['requires_research_facilities'];

        if ($required > 0 && $this->researchFacilityCount($corporation) < $required) {
            throw ValidationException::withMessages([
                'technology_type_id' => sprintf(
                    '%s needs %d Research Facilities, and %s has %d.',
                    $technology->name,
                    $required,
                    $corporation->name,
                    $this->researchFacilityCount($corporation),
                ),
            ]);
        }

        $amounts = $grant['amounts'];
        $suits = array_values($suits);

        if (count($suits) !== count($amounts)) {
            throw ValidationException::withMessages([
                'suits' => sprintf(
                    '%s is paid in %d suit(s), each chosen by you.',
                    $technology->name,
                    count($amounts),
                ),
            ]);
        }

        if (count(array_unique(array_map(fn (ResearchSuit $suit): string => $suit->value, $suits))) !== count($suits)) {
            throw ValidationException::withMessages([
                'suits' => 'Each part of the price has to be paid in a different suit.',
            ]);
        }

        return DB::transaction(function () use (
            $corporation,
            $technology,
            $grant,
            $amounts,
            $suits,
            $value,
            $actor,
        ): ResearchCard {
            foreach ($amounts as $index => $amount) {
                $suit = $suits[$index];
                $held = $corporation->researchPointsIn($suit);

                if ($held < $amount) {
                    throw ValidationException::withMessages([
                        'suits' => sprintf(
                            '%s has %d %s Research Point(s) and needs %d.',
                            $corporation->name,
                            $held,
                            $suit->label(),
                            $amount,
                        ),
                    ]);
                }

                $this->trackers->adjust(
                    $corporation,
                    $suit->tracker(),
                    -$amount,
                    'Deck customisation: '.$technology->name,
                    $actor,
                );
            }

            // Into the deck rather than into the hand: the tree says "add a card
            // to your research deck", and a card that arrived in hand would be
            // a free draw on top of the purchase.
            $card = $corporation->researchCards()->create([
                'game_id' => $corporation->game_id,
                'suit' => $grant['wild'] ? null : $suits[0],
                'value' => $value,
                'zone' => ResearchZone::Deck,
                'position' => 0,
                'restriction' => $grant['restriction'],
            ]);

            $this->shuffleDeck($corporation->game, $corporation);

            return $card->refresh();
        });
    }

    /**
     * Change a card already in a deck.
     *
     * Control's, and the other half of 3.2.3: the rulebook offers "adding cards
     * to your deck or upgrading your existing cards", and the tree prices only
     * the adding. An upgrade is therefore a custom proposal under 3.2.4 -
     * Research Control names a price, takes the points with the tracker
     * controls, and edits the card here.
     */
    public function editCard(
        ResearchCard $card,
        ?ResearchSuit $suit,
        int $value,
        ?ResearchCardRestriction $restriction = null,
    ): ResearchCard {
        if ($value < 1) {
            throw ValidationException::withMessages([
                'value' => 'A research card is worth at least 1.',
            ]);
        }

        $card->forceFill([
            'suit' => $suit,
            'value' => $value,
            'restriction' => $restriction,
        ])->save();

        return $card;
    }

    /**
     * The cards a Corporation is holding.
     *
     * @return Collection<int, ResearchCard>
     */
    public function hand(Corporation $corporation): Collection
    {
        return $corporation->researchCards()
            ->inZone(ResearchZone::Hand)
            ->orderBy('suit')
            ->orderBy('value')
            ->get();
    }

    /**
     * The cards face up in the shared pool.
     *
     * @return Collection<int, ResearchCard>
     */
    public function pool(Game $game): Collection
    {
        return $game->researchCards()
            ->publicCards()
            ->inZone(ResearchZone::Pool)
            ->orderBy('position')
            ->get();
    }

    /**
     * How many cards are left in a deck - a Corporation's, or the public one.
     */
    public function deckCount(Game $game, ?Corporation $corporation): int
    {
        $query = $game->researchCards()->inZone(ResearchZone::Deck);

        return $corporation === null
            ? $query->whereNull('corporation_id')->count()
            : $query->where('corporation_id', $corporation->id)->count();
    }

    /**
     * Every card in a deck, whatever zone it is in.
     *
     * @return Collection<int, ResearchCard>
     */
    public function deck(Game $game, ?Corporation $corporation): Collection
    {
        $query = $game->researchCards()->orderBy('suit')->orderBy('value');

        return $corporation === null
            ? $query->whereNull('corporation_id')->get()
            : $query->where('corporation_id', $corporation->id)->get();
    }

    /**
     * Draw a Corporation's hand back up to the limit.
     *
     * Returns false when the deck could not fill it, which is the rulebook's
     * "You have no cards left in your deck when you try to draw up your hand
     * limit at the end of your turn" - the one automatic way out of the game.
     */
    private function drawUp(ResearchSession $session, Corporation $corporation): bool
    {
        $held = $corporation->researchCards()->inZone(ResearchZone::Hand)->count();
        $wanted = $this->handSize() - $held;

        if ($wanted <= 0) {
            return true;
        }

        $drawn = $corporation->researchCards()
            ->inZone(ResearchZone::Deck)
            ->orderBy('position')
            ->limit($wanted)
            ->get();

        foreach ($drawn as $card) {
            $card->forceFill(['zone' => ResearchZone::Hand, 'position' => 0])->save();
        }

        return $drawn->count() === $wanted;
    }

    /**
     * Top the public pool back up to six.
     */
    private function refillPool(Game $game): void
    {
        $wanted = $this->poolSize() - $game->researchCards()
            ->publicCards()
            ->inZone(ResearchZone::Pool)
            ->count();

        if ($wanted <= 0) {
            return;
        }

        $drawn = $game->researchCards()
            ->publicCards()
            ->inZone(ResearchZone::Deck)
            ->orderBy('position')
            ->limit($wanted)
            ->get();

        $next = (int) $game->researchCards()
            ->publicCards()
            ->inZone(ResearchZone::Pool)
            ->max('position');

        foreach ($drawn as $card) {
            $card->forceFill(['zone' => ResearchZone::Pool, 'position' => ++$next])->save();
        }
    }

    /**
     * Every card back into its own deck, in a new order.
     */
    private function gather(Game $game): void
    {
        $game->researchCards()->update(['zone' => ResearchZone::Deck, 'position' => 0]);

        $this->shuffleDeck($game, null);

        foreach ($game->corporations()->get() as $corporation) {
            $this->shuffleDeck($game, $corporation);
        }
    }

    /**
     * Rewrite one deck's positions in a random order.
     *
     * A shuffle is a rewrite of the position column rather than a reordering of
     * rows, so drawing is always "the lowest position left".
     */
    private function shuffleDeck(Game $game, ?Corporation $corporation): void
    {
        $query = $game->researchCards()->inZone(ResearchZone::Deck);

        $ids = ($corporation === null
            ? $query->whereNull('corporation_id')
            : $query->where('corporation_id', $corporation->id)
        )->pluck('id')->shuffle()->values();

        foreach ($ids as $offset => $id) {
            ResearchCard::query()->whereKey($id)->update(['position' => $offset + 1]);
        }
    }

    /**
     * Seat every Corporation, in a random order (rulebook 3.2.1).
     */
    private function seat(ResearchSession $session, Game $game): void
    {
        $corporations = $game->corporations()->get()->shuffle()->values();

        foreach ($corporations as $offset => $corporation) {
            $session->seats()->create([
                'corporation_id' => $corporation->id,
                'order' => $offset + 1,
            ]);
        }

        $this->setTurnToFirstPlayer($session);
    }

    /**
     * Deal the opening hands and the pool.
     */
    private function deal(ResearchSession $session): void
    {
        foreach ($session->game->corporations()->get() as $corporation) {
            $this->drawUp($session, $corporation);
        }

        $this->refillPool($session->game);
    }

    private function setTurnToFirstPlayer(ResearchSession $session): ResearchSession
    {
        $first = $session->seats()->whereNull('left_at')->orderBy('order')->first();

        $session->forceFill(['current_order' => $first?->order])->save();

        return $session;
    }

    private function seatFor(ResearchSession $session, Corporation $corporation): ResearchSeat
    {
        /** @var ResearchSeat|null $seat */
        $seat = $session->seats()->where('corporation_id', $corporation->id)->first();

        if ($seat === null) {
            throw ValidationException::withMessages([
                'equation' => $corporation->name.' has no seat at this research table.',
            ]);
        }

        return $seat;
    }

    /**
     * Turn card ids into cards, refusing anything the Corporation cannot play.
     *
     * A card has to be in this Corporation's hand or face up in the public pool,
     * and it cannot appear twice - not twice on one side, and not once on each.
     * The other side is passed in for exactly that check.
     *
     * @param  array<int, int>  $ids
     * @param  array<int, int>  $otherSide
     * @return array<int, ResearchCard>
     */
    private function resolveCards(Corporation $corporation, array $ids, array $otherSide): array
    {
        $ids = array_map('intval', array_values($ids));

        $duplicates = count($ids) !== count(array_unique($ids))
            || array_intersect($ids, array_map('intval', array_values($otherSide))) !== [];

        if ($duplicates) {
            throw ValidationException::withMessages([
                'equation' => 'A card can only be used once in an equation.',
            ]);
        }

        /** @var Collection<int, ResearchCard> $cards */
        $cards = ResearchCard::query()
            ->whereKey($ids)
            ->where('game_id', $corporation->game_id)
            ->get()
            ->keyBy('id');

        $resolved = [];

        foreach ($ids as $id) {
            $card = $cards->get($id);

            $playable = $card !== null
                && $card->zone->isPlayable()
                && ($card->corporation_id === null || $card->corporation_id === $corporation->id)
                // A hand card belongs to the Corporation; a pool card to
                // nobody. A card in another Corporation's hand is neither.
                && ($card->corporation_id !== null) === ($card->zone === ResearchZone::Hand);

            if (! $playable) {
                throw ValidationException::withMessages([
                    'equation' => 'One of those cards is not in your hand or the public pool.',
                ]);
            }

            $resolved[] = $card;
        }

        return $resolved;
    }

    /**
     * The cards of one side, as they were when they were played.
     *
     * @param  array<int, ResearchCard>  $cards
     * @return array<int, array{suit: string|null, value: int, from_hand: bool, restriction: string|null}>
     */
    private function snapshot(array $cards): array
    {
        return array_map(fn (ResearchCard $card): array => [
            'suit' => $card->suit?->value,
            'value' => $card->value,
            'from_hand' => ! $card->isPublic(),
            'restriction' => $card->restriction?->value,
        ], $cards);
    }

    /**
     * Move Research Points for an equation, in whichever direction.
     *
     * @param  array<string, int>  $awards  points by suit value
     */
    private function payAwards(
        ResearchEquation $equation,
        array $awards,
        ?User $actor,
        string $reason,
    ): void {
        foreach ($awards as $suitValue => $points) {
            if ($points === 0) {
                continue;
            }

            $this->trackers->adjust(
                $equation->corporation,
                ResearchSuit::from((string) $suitValue)->tracker(),
                $points,
                sprintf('%s #%d', $reason, $equation->id),
                $actor,
            );
        }
    }

    /**
     * How many open Research Facilities a Corporation has, which is what the
     * later deck customisation rows ask for.
     */
    private function researchFacilityCount(Corporation $corporation): int
    {
        $turn = $corporation->game->currentTurn();

        return $corporation->facilities()
            ->availableOnTurn($turn->number ?? Facility::FIRST_TURN)
            ->whereHas(
                'facilityType',
                fn ($query) => $query->where('key', FacilityTypeBlueprint::RESEARCH),
            )
            ->count();
    }
}
