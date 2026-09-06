<?php

namespace App\Services;

use App\Enums\AgendaCardStatus;
use App\Enums\AgendaItemSource;
use App\Enums\CouncilAttendance;
use App\Enums\PhaseType;
use App\Enums\ResolutionAmendment;
use App\Enums\Tracker;
use App\Models\AgendaCard;
use App\Models\AgendaResolution;
use App\Models\Character;
use App\Models\Corporation;
use App\Models\CouncilAgendaItem;
use App\Models\CouncilBallot;
use App\Models\CouncilSeat;
use App\Models\CouncilSession;
use App\Models\Game;
use App\Models\TrackerAdjustment;
use App\Models\Turn;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The Council's rules (rulebook 3.1): the agenda, the Chair's powers over it,
 * and the Political-Will-weighted vote.
 *
 * Two things this deliberately does not do.
 *
 * It never moves Political Will for a vote. The rulebook has a CEO write down
 * the Political Will they *have* - it is the weight of the vote, not its price,
 * and nothing in 3.1 spends it. The one movement the rulebook does describe is
 * the cost of missing a sitting, and even that is Control's to apply rather than
 * this service's to work out, because no figure is printed anywhere.
 *
 * And it never decides anything the Chair is supposed to decide. A tie is not
 * broken by a rule here; it is handed back to the Chair, which is what 3.1.2
 * says happens.
 */
class CouncilService
{
    public function __construct(private readonly TrackerService $trackers) {}

    // -----------------------------------------------------------------
    // The sitting
    // -----------------------------------------------------------------

    /**
     * Open this turn's sitting, or return the one already open.
     *
     * Called when the Setup phase starts, because that is when the Council
     * takes its seats and when the recess clock starts running. Idempotent: a
     * phase restarted or a session already opened by hand is left as it is,
     * chair included, so Control's override survives.
     */
    public function openSession(Turn $turn, ?CarbonInterface $sittingStartedAt = null): CouncilSession
    {
        $existing = $turn->councilSession()->first();

        if ($existing !== null) {
            return $existing;
        }

        $startedAt = $sittingStartedAt ?? Carbon::now();
        $recessSeconds = (int) $turn->game->council_recess_seconds;

        /** @var CouncilSession $session */
        $session = $turn->councilSession()->create([
            'chair_corporation_id' => $this->nextChair($turn)?->id,
            'recess_at' => $startedAt->copy()->addSeconds($recessSeconds),
        ]);

        return $session;
    }

    /**
     * Whose turn it is to chair, from the rotation the game holds.
     *
     * The rotation is an order Council Control announces on the day, so nothing
     * here invents one: it reads the order held against the Corporations and
     * steps through it by turn number. A Corporation Control has not placed in
     * the rotation sorts last rather than being left out, because a Corporation
     * with a CEO has a seat whether or not anybody has ordered it.
     */
    public function nextChair(Turn $turn): ?Corporation
    {
        $rotation = $this->rotation($turn->game);

        if ($rotation->isEmpty()) {
            return null;
        }

        return $rotation[($turn->number - 1) % $rotation->count()];
    }

    /**
     * @return Collection<int, Corporation>
     */
    public function rotation(Game $game): Collection
    {
        return $game->corporations()
            ->orderByRaw('council_chair_order is null')
            ->orderBy('council_chair_order')
            ->orderBy('id')
            ->get();
    }

    /**
     * Hand the Chair to somebody else for this sitting.
     *
     * Only this sitting: the rotation itself is untouched, so a Chair swapped
     * because somebody stepped out does not shuffle every turn after it.
     */
    public function setChair(CouncilSession $session, ?Corporation $corporation): CouncilSession
    {
        if ($corporation !== null && $corporation->game_id !== $session->turn->game_id) {
            throw ValidationException::withMessages([
                'chair_corporation_id' => 'That Corporation is playing a different game.',
            ]);
        }

        $session->forceFill(['chair_corporation_id' => $corporation?->id])->save();

        return $session;
    }

    /**
     * Move the moment the Council goes into recess.
     *
     * Absolute, like the phase's own deadline, and mutated directly for the
     * same reason: the browser derives what is left of it and must never be
     * able to make the Council sit longer than Control has allowed.
     */
    public function shiftRecess(CouncilSession $session, int $seconds): CouncilSession
    {
        if ($session->recess_at === null) {
            return $session;
        }

        $session->forceFill(['recess_at' => $session->recess_at->copy()->addSeconds($seconds)])->save();

        return $session;
    }

    public function setRecessAt(CouncilSession $session, ?CarbonInterface $recessAt): CouncilSession
    {
        $session->forceFill(['recess_at' => $recessAt])->save();

        return $session;
    }

    // -----------------------------------------------------------------
    // Establishing the agenda (3.1.1)
    // -----------------------------------------------------------------

    /**
     * Control picks cards out of the deck and hands them to the Chair (3.1.1).
     *
     * Picked rather than dealt. The rulebook says Control draws three, and at
     * the table Control is holding the deck and reading it - so which three the
     * Council is asked about is a judgement Control makes about the game in
     * front of it, not a shuffle. Three is what the Control panel offers and
     * what the Chair then keeps two of; handing over a different number is
     * Control's to do, and the Chair keeps two of whatever arrives.
     *
     * This sets the hand rather than adding to it, so a card picked by mistake
     * is taken back by handing the corrected set over again: anything dropped
     * goes back to the deck. That stops once the Chair has chosen, because by
     * then the discard of 3.1.1 has happened and the agenda is the Chair's.
     *
     * @param  array<int, int>  $cardIds
     * @return Collection<int, AgendaCard>
     */
    public function handToChair(CouncilSession $session, array $cardIds): Collection
    {
        if ($this->chairHasChosen($session)) {
            throw ValidationException::withMessages([
                'cards' => 'The Chair has already chosen from this turn\'s cards.',
            ]);
        }

        $cardIds = array_values(array_unique(array_map('intval', $cardIds)));

        if ($cardIds === []) {
            throw ValidationException::withMessages([
                'cards' => 'Pick the cards to hand to the Chair.',
            ]);
        }

        // More than the Council could vote on in a turn is not a hand, it is
        // the deck. The Chair still only keeps two of it.
        if (count($cardIds) > CouncilSession::MAXIMUM_ITEMS) {
            throw ValidationException::withMessages([
                'cards' => sprintf(
                    'The Council votes on at most %d items a turn, so there is no point handing over more.',
                    CouncilSession::MAXIMUM_ITEMS,
                ),
            ]);
        }

        /** @var Collection<int, AgendaCard> $picked */
        $picked = $session->turn->game->agendaCards()
            ->whereIn('id', $cardIds)
            ->whereIn('status', [AgendaCardStatus::Deck, AgendaCardStatus::InHand])
            ->get();

        if ($picked->count() !== count($cardIds)) {
            throw ValidationException::withMessages([
                'cards' => 'One of those cards is not in the deck.',
            ]);
        }

        return DB::transaction(function () use ($session, $picked): Collection {
            // Anything Control has dropped since last time goes back to the
            // deck, and takes its place in this sitting with it.
            $returned = $this->currentHand($session)
                ->whereNotIn('id', $picked->pluck('id')->all());

            foreach ($returned as $card) {
                $card->forceFill(['status' => AgendaCardStatus::Deck])->save();

                $session->items()->where('agenda_card_id', $card->id)->delete();
            }

            foreach ($picked as $card) {
                $card->forceFill(['status' => AgendaCardStatus::InHand])->save();

                $session->items()->firstOrCreate(
                    ['agenda_card_id' => $card->id],
                    ['source' => AgendaItemSource::Handed],
                );
            }

            $session->forceFill(['handed_at' => Carbon::now()])->save();

            return $picked;
        });
    }

    /**
     * What the Chair is holding: the cards Control has handed over and the
     * Chair has not yet kept or discarded.
     *
     * @return Collection<int, AgendaCard>
     */
    public function currentHand(CouncilSession $session): Collection
    {
        return AgendaCard::query()
            ->whereIn('id', $session->items()
                ->where('source', AgendaItemSource::Handed)
                ->pluck('agenda_card_id'))
            ->where('status', AgendaCardStatus::InHand)
            ->orderBy('id')
            ->get();
    }

    /**
     * Whether the Chair has already kept two out of what Control handed over.
     *
     * Read from the cards rather than from a flag on the sitting, because that
     * is where it is written: a handed card that is no longer in the hand was
     * either kept or discarded, and either way the choice is made.
     */
    public function chairHasChosen(CouncilSession $session): bool
    {
        return $session->items()
            ->where('source', AgendaItemSource::Handed)
            ->whereHas('card', fn ($query) => $query->where('status', '!=', AgendaCardStatus::InHand))
            ->exists();
    }

    /**
     * The Chair keeps two of what Control handed over, and the rest are
     * discarded (3.1.1).
     *
     * @param  array<int, int>  $keptCardIds
     * @return Collection<int, AgendaCard>
     */
    public function keep(CouncilSession $session, array $keptCardIds): Collection
    {
        $handed = $this->currentHand($session);

        if ($handed->isEmpty()) {
            throw ValidationException::withMessages([
                'kept' => 'There is nothing in the Chair\'s hand to keep.',
            ]);
        }

        $kept = $handed->whereIn('id', $keptCardIds);

        if ($kept->count() !== count(array_unique($keptCardIds))) {
            throw ValidationException::withMessages([
                'kept' => 'One of those cards is not in the Chair\'s hand.',
            ]);
        }

        // Two, unless Control handed over fewer than two to begin with.
        $allowed = min(CouncilSession::CARDS_KEPT, $handed->count());

        if ($kept->count() !== $allowed) {
            throw ValidationException::withMessages([
                'kept' => sprintf('The Chair keeps %d of the %d handed over.', $allowed, $handed->count()),
            ]);
        }

        return DB::transaction(function () use ($session, $handed, $kept): Collection {
            foreach ($kept as $card) {
                $this->tableCard($session, $card);
            }

            foreach ($handed->whereNotIn('id', $kept->pluck('id')->all()) as $card) {
                $card->forceFill(['status' => AgendaCardStatus::Discarded])->save();
            }

            return $kept->values();
        });
    }

    /**
     * Put a card up for vote this turn, subject to the five-item cap (3.1.3).
     *
     * The item may already exist - a card Control handed over has had one
     * since it was handed - in which case this only moves it onto the table.
     */
    public function tableCard(CouncilSession $session, AgendaCard $card, ?AgendaItemSource $source = null): CouncilAgendaItem
    {
        if ($card->game_id !== $session->turn->game_id) {
            throw ValidationException::withMessages([
                'agenda_card_id' => 'That card belongs to a different game.',
            ]);
        }

        if ($this->tabledCount($session) >= CouncilSession::MAXIMUM_ITEMS) {
            throw ValidationException::withMessages([
                'agenda_card_id' => sprintf(
                    'The Council already has its %d agenda items for this turn.',
                    CouncilSession::MAXIMUM_ITEMS,
                ),
            ]);
        }

        // A card the Council could not choose between is not ready to be voted
        // on. 3.1.4 puts the floor at two resolutions, and an addition waiting
        // on Control's sign-off is not one of them yet.
        if ($card->votableResolutions()->count() < AgendaCard::MINIMUM_RESOLUTIONS) {
            throw ValidationException::withMessages([
                'agenda_card_id' => sprintf(
                    '%s needs at least %d resolutions to be voted on.',
                    $card->title,
                    AgendaCard::MINIMUM_RESOLUTIONS,
                ),
            ]);
        }

        return DB::transaction(function () use ($session, $card, $source): CouncilAgendaItem {
            /** @var CouncilAgendaItem $item */
            $item = $session->items()->firstOrCreate(
                ['agenda_card_id' => $card->id],
                ['source' => $source ?? AgendaItemSource::Handed],
            );

            $card->forceFill(['status' => AgendaCardStatus::Tabled])->save();

            return $item;
        });
    }

    /**
     * How many cards this sitting is voting on, which is what the cap counts.
     */
    public function tabledCount(CouncilSession $session): int
    {
        return $session->items()
            ->whereHas('card', fn ($query) => $query->whereIn('status', [
                AgendaCardStatus::Tabled->value,
                AgendaCardStatus::Voted->value,
            ]))
            ->count();
    }

    /**
     * At the start of Setup the Chair may promote one previously submitted item
     * (3.1.3). One: the rulebook says "one item", and the important pile is
     * meant to be a queue rather than a store the Chair can empty in a turn.
     */
    public function promote(CouncilSession $session, AgendaCard $card): CouncilAgendaItem
    {
        if ($card->status !== AgendaCardStatus::Important) {
            throw ValidationException::withMessages([
                'agenda_card_id' => 'Only an item the Chair has held as important can be promoted.',
            ]);
        }

        $alreadyPromoted = $session->items()
            ->where('source', AgendaItemSource::Promoted)
            ->exists();

        if ($alreadyPromoted) {
            throw ValidationException::withMessages([
                'agenda_card_id' => 'The Chair has already promoted an item this turn.',
            ]);
        }

        return $this->tableCard($session, $card, AgendaItemSource::Promoted);
    }

    // -----------------------------------------------------------------
    // Custom agendas (3.1.3)
    // -----------------------------------------------------------------

    /**
     * Write a card into the game's deck.
     *
     * The only way anything gets into it, seeding included: App\Actions\SeedAgendaCards
     * lays the game's own deck down through here rather than writing rows, so
     * the two-to-five bound of 3.1.4 holds for a seeded card exactly as it does
     * for one Control invents at the table.
     *
     * @param  array<int, string>  $resolutions
     */
    public function createDeckCard(Game $game, string $title, ?string $body, array $resolutions): AgendaCard
    {
        return DB::transaction(function () use ($game, $title, $body, $resolutions): AgendaCard {
            /** @var AgendaCard $card */
            $card = $game->agendaCards()->create([
                'title' => $title,
                'body' => $body,
                'status' => AgendaCardStatus::Deck,
            ]);

            $this->replaceResolutions($card, $resolutions);

            return $card->fresh(['resolutions']) ?? $card;
        });
    }

    /**
     * A player fills out a blank agenda card from the Council Chamber.
     *
     * @param  array<int, string>  $resolutions
     */
    public function draftCustomCard(Character $author, string $title, ?string $body, array $resolutions): AgendaCard
    {
        return DB::transaction(function () use ($author, $title, $body, $resolutions): AgendaCard {
            /** @var AgendaCard $card */
            $card = AgendaCard::query()->create([
                'game_id' => $author->game_id,
                'submitted_by_character_id' => $author->id,
                'title' => $title,
                'body' => $body,
                'status' => AgendaCardStatus::Draft,
            ]);

            $this->replaceResolutions($card, $resolutions);

            return $card->fresh(['resolutions']) ?? $card;
        });
    }

    /**
     * Rewrite a card that is still in its author's hands.
     *
     * @param  array<int, string>  $resolutions
     */
    public function reviseCard(AgendaCard $card, string $title, ?string $body, array $resolutions): AgendaCard
    {
        if (! $card->status->isEditableByAuthor()) {
            throw ValidationException::withMessages([
                'title' => 'That card is with '.($card->status === AgendaCardStatus::WithControl ? 'Control' : 'the Chair').'.',
            ]);
        }

        return $this->rewriteCard($card, $title, $body, $resolutions);
    }

    /**
     * Rewrite a card outright, resolutions and all. Control's, and the deck's:
     * a card in the deck has no author to ask and no votes to disturb.
     *
     * The one thing Control does not get to do is rewrite a card that has been
     * voted on. The ballots point at those resolutions, so replacing them would
     * quietly change what the Council agreed to - which is the one edit a
     * ledger could not explain afterwards.
     *
     * @param  array<int, string>  $resolutions
     */
    public function rewriteCard(AgendaCard $card, string $title, ?string $body, array $resolutions): AgendaCard
    {
        if ($card->items()->whereNotNull('resolved_at')->exists()) {
            throw ValidationException::withMessages([
                'resolutions' => 'That card has already been voted on.',
            ]);
        }

        return DB::transaction(function () use ($card, $title, $body, $resolutions): AgendaCard {
            $card->forceFill(['title' => $title, 'body' => $body])->save();

            $this->replaceResolutions($card, $resolutions);

            return $card->fresh(['resolutions']) ?? $card;
        });
    }

    /**
     * Give the card to Control, who adds any remarks (3.1.3).
     */
    public function submitToControl(AgendaCard $card): AgendaCard
    {
        $this->assertCustom($card);
        $this->assertResolutionBounds($card);

        if (! in_array($card->status, [AgendaCardStatus::Draft, AgendaCardStatus::Annotated, AgendaCardStatus::Rejected], true)) {
            throw ValidationException::withMessages(['status' => 'That card is not the player\'s to hand over.']);
        }

        $card->forceFill(['status' => AgendaCardStatus::WithControl])->save();

        return $card;
    }

    /**
     * Control's remarks, and the card back to the player.
     *
     * The card goes back rather than on to the Chair, because the rulebook has
     * the player submit it once they and Control agree - so agreeing is the
     * player's to do too.
     */
    public function annotate(AgendaCard $card, ?string $note): AgendaCard
    {
        $this->assertCustom($card);

        $card->forceFill([
            'control_note' => $note,
            'status' => AgendaCardStatus::Annotated,
        ])->save();

        return $card;
    }

    /**
     * The player submits the annotated card to the Chair.
     */
    public function submitToChair(AgendaCard $card): AgendaCard
    {
        $this->assertCustom($card);
        $this->assertResolutionBounds($card);

        if (! in_array($card->status, [AgendaCardStatus::Annotated, AgendaCardStatus::Rejected], true)) {
            throw ValidationException::withMessages([
                'status' => 'Control has not seen that card yet.',
            ]);
        }

        $card->forceFill(['status' => AgendaCardStatus::WithChair])->save();

        return $card;
    }

    /**
     * Accept as urgent: voted on this turn, if there is room (3.1.3).
     */
    public function acceptUrgent(CouncilSession $session, AgendaCard $card): CouncilAgendaItem
    {
        $this->assertWithChair($card);

        return $this->tableCard($session, $card, AgendaItemSource::Urgent);
    }

    /**
     * Accept as important: eligible for a future turn.
     */
    public function acceptImportant(AgendaCard $card): AgendaCard
    {
        $this->assertWithChair($card);

        $card->forceFill(['status' => AgendaCardStatus::Important])->save();

        return $card;
    }

    /**
     * Reject: the card goes back to the player, who may try a future Chair.
     */
    public function reject(AgendaCard $card): AgendaCard
    {
        $this->assertWithChair($card);

        $card->forceFill(['status' => AgendaCardStatus::Rejected])->save();

        return $card;
    }

    // -----------------------------------------------------------------
    // Amending agendas (3.1.4)
    // -----------------------------------------------------------------

    /**
     * Propose a new resolution. It is not an option until Control signs it off.
     */
    public function proposeAddition(AgendaCard $card, string $text, ?Character $chair = null): AgendaResolution
    {
        if ($card->votableResolutions()->count() >= AgendaCard::MAXIMUM_RESOLUTIONS) {
            throw ValidationException::withMessages([
                'text' => sprintf('An agenda may have at most %d resolutions.', AgendaCard::MAXIMUM_RESOLUTIONS),
            ]);
        }

        /** @var AgendaResolution $resolution */
        $resolution = $card->resolutions()->create([
            'position' => ((int) $card->resolutions()->max('position')) + 1,
            'text' => $text,
            'pending_amendment' => ResolutionAmendment::Addition,
            'proposed_by_character_id' => $chair?->id,
        ]);

        return $resolution;
    }

    public function proposeRemoval(AgendaResolution $resolution, ?Character $chair = null): AgendaResolution
    {
        $this->assertAmendable($resolution);

        $card = $resolution->card;

        if ($card->votableResolutions()->count() <= AgendaCard::MINIMUM_RESOLUTIONS) {
            throw ValidationException::withMessages([
                'resolution' => sprintf('An agenda cannot have fewer than %d resolutions.', AgendaCard::MINIMUM_RESOLUTIONS),
            ]);
        }

        $resolution->forceFill([
            'pending_amendment' => ResolutionAmendment::Removal,
            'pending_text' => null,
            'proposed_by_character_id' => $chair?->id,
        ])->save();

        return $resolution;
    }

    public function proposeRewording(AgendaResolution $resolution, string $text, ?Character $chair = null): AgendaResolution
    {
        $this->assertAmendable($resolution);

        $resolution->forceFill([
            'pending_amendment' => ResolutionAmendment::Rewording,
            'pending_text' => $text,
            'proposed_by_character_id' => $chair?->id,
        ])->save();

        return $resolution;
    }

    /**
     * Council Control signs the amendment off, and only then does the card
     * change (3.1.4).
     */
    public function signOffAmendment(AgendaResolution $resolution): AgendaResolution
    {
        $amendment = $resolution->pending_amendment;

        if ($amendment === null) {
            throw ValidationException::withMessages([
                'resolution' => 'There is no amendment on that resolution.',
            ]);
        }

        $card = $resolution->card;
        $votable = $card->votableResolutions()->count();

        // Checked again here rather than trusted from the proposal: two
        // amendments can be waiting at once, and signing both off is what would
        // take a card past its bounds.
        if ($amendment === ResolutionAmendment::Addition && $votable >= AgendaCard::MAXIMUM_RESOLUTIONS) {
            throw ValidationException::withMessages([
                'resolution' => sprintf('An agenda may have at most %d resolutions.', AgendaCard::MAXIMUM_RESOLUTIONS),
            ]);
        }

        if ($amendment === ResolutionAmendment::Removal && $votable <= AgendaCard::MINIMUM_RESOLUTIONS) {
            throw ValidationException::withMessages([
                'resolution' => sprintf('An agenda cannot have fewer than %d resolutions.', AgendaCard::MINIMUM_RESOLUTIONS),
            ]);
        }

        $resolution->forceFill(match ($amendment) {
            ResolutionAmendment::Addition => [
                'pending_amendment' => null,
            ],
            ResolutionAmendment::Removal => [
                'pending_amendment' => null,
                'removed_at' => Carbon::now(),
            ],
            ResolutionAmendment::Rewording => [
                'pending_amendment' => null,
                'text' => $resolution->pending_text ?? $resolution->text,
                'pending_text' => null,
            ],
        })->save();

        return $resolution;
    }

    /**
     * Control refuses the amendment. A proposed addition never existed, so it
     * goes away entirely; anything else simply stands as it was.
     */
    public function rejectAmendment(AgendaResolution $resolution): void
    {
        if ($resolution->pending_amendment === ResolutionAmendment::Addition) {
            $resolution->delete();

            return;
        }

        $resolution->forceFill([
            'pending_amendment' => null,
            'pending_text' => null,
        ])->save();
    }

    // -----------------------------------------------------------------
    // Voting (3.1.2)
    // -----------------------------------------------------------------

    /**
     * The Chair declares the vote secret, or public again.
     *
     * Declaring secrecy must happen before any votes are submitted, and any
     * that are already in are handed back. Both halves of that are the same
     * act: a ballot cast in the open was cast knowing it would be read out, so
     * it cannot be quietly carried over into a secret ballot.
     *
     * Going the other way is refused once anybody has voted, which the rulebook
     * does not say and this does: a ballot cast under a promise of secrecy must
     * not be exposed by the Chair changing their mind afterwards. The Chair may
     * still leak the breakdown as they see fit - that is theirs to do, and it
     * is not the same as the application publishing it.
     *
     * @return int how many ballots were handed back
     */
    public function declareSecret(CouncilAgendaItem $item, bool $secret, ?string $reason = null): int
    {
        if ($item->isResolved()) {
            throw ValidationException::withMessages([
                'secret' => 'That vote has already been resolved.',
            ]);
        }

        if ($item->secret === $secret) {
            return 0;
        }

        if (! $secret) {
            if ($item->liveBallots()->exists()) {
                throw ValidationException::withMessages([
                    'secret' => 'Votes have already been cast in secret. They would have to be handed back first.',
                ]);
            }

            $item->forceFill(['secret' => false, 'secret_declared_at' => null])->save();

            return 0;
        }

        return DB::transaction(function () use ($item, $reason): int {
            $returned = 0;

            foreach ($item->liveBallots()->get() as $ballot) {
                $this->returnBallot($ballot, $reason ?? 'The Chair declared this vote secret.');
                $returned++;
            }

            $item->forceFill(['secret' => true, 'secret_declared_at' => Carbon::now()])->save();

            return $returned;
        });
    }

    /**
     * Submit a Corporation's vote to the Chair.
     *
     * The Political Will is split however the CEO likes across the resolutions
     * on the card, and the total is capped by what the Corporation holds. It is
     * not taken from them: the vote is weighted by Political Will, and nothing
     * in 3.1 spends it.
     *
     * @param  array<int, int>  $allocations  resolution id => Political Will
     */
    public function castBallot(
        CouncilAgendaItem $item,
        Corporation $corporation,
        array $allocations,
        ?Character $character = null,
        ?User $user = null,
    ): CouncilBallot {
        if ($item->isResolved()) {
            throw ValidationException::withMessages([
                'allocations' => 'That vote has already been resolved.',
            ]);
        }

        $card = $item->card;

        if ($card->game_id !== $corporation->game_id) {
            throw ValidationException::withMessages([
                'allocations' => 'That Corporation is playing a different game.',
            ]);
        }

        // One live ballot per Corporation. Changing a vote means asking the
        // Chair for the slip back, exactly as it would at the table.
        if ($item->liveBallots()->where('corporation_id', $corporation->id)->exists()) {
            throw ValidationException::withMessages([
                'allocations' => 'Your vote is already with the Chair. Ask for it back to change it.',
            ]);
        }

        $votable = $card->votableResolutions()->keyBy('id');
        $allocations = array_filter(
            array_map('intval', $allocations),
            fn (int $will): bool => $will > 0,
        );

        if ($allocations === []) {
            throw ValidationException::withMessages([
                'allocations' => 'A vote has to put Political Will behind something.',
            ]);
        }

        foreach (array_keys($allocations) as $resolutionId) {
            if (! $votable->has($resolutionId)) {
                throw ValidationException::withMessages([
                    'allocations' => 'One of those resolutions is not on this agenda.',
                ]);
            }
        }

        $total = array_sum($allocations);

        if ($total > $corporation->political_will) {
            throw ValidationException::withMessages([
                'allocations' => sprintf(
                    '%s holds %d Political Will and that vote spreads %d.',
                    $corporation->name,
                    $corporation->political_will,
                    $total,
                ),
            ]);
        }

        return DB::transaction(function () use ($item, $corporation, $allocations, $character, $user): CouncilBallot {
            /** @var CouncilBallot $ballot */
            $ballot = $item->ballots()->create([
                'corporation_id' => $corporation->id,
                'character_id' => $character?->id,
                'user_id' => $user?->id,
                'submitted_at' => Carbon::now(),
            ]);

            foreach ($allocations as $resolutionId => $will) {
                $ballot->allocations()->create([
                    'agenda_resolution_id' => $resolutionId,
                    'political_will' => $will,
                ]);
            }

            return $ballot->fresh(['allocations']) ?? $ballot;
        });
    }

    /**
     * Hand a ballot back, so the Corporation may vote again.
     */
    public function returnBallot(CouncilBallot $ballot, string $reason): CouncilBallot
    {
        if ($ballot->wasReturned()) {
            return $ballot;
        }

        if ($ballot->item->isResolved()) {
            throw ValidationException::withMessages([
                'ballot' => 'That vote has already been resolved.',
            ]);
        }

        $ballot->forceFill([
            'returned_at' => Carbon::now(),
            'returned_reason' => $reason,
        ])->save();

        return $ballot;
    }

    /**
     * What the votes add up to, per resolution.
     *
     * "Simple majority" with up to five resolutions on a card is read as the
     * most Political Will: there may be no absolute majority to be had, and the
     * rulebook's own remedy for an undecided vote is the Chair rather than a
     * second round.
     *
     * @return array{totals: array<int, int>, leaders: array<int, int>, tied: bool, cast: int}
     */
    public function tally(CouncilAgendaItem $item): array
    {
        $totals = [];

        foreach ($item->card->votableResolutions() as $resolution) {
            $totals[$resolution->id] = 0;
        }

        $ballots = $item->liveBallots()->with('allocations')->get();

        foreach ($ballots as $ballot) {
            foreach ($ballot->allocations as $allocation) {
                if (array_key_exists($allocation->agenda_resolution_id, $totals)) {
                    $totals[$allocation->agenda_resolution_id] += $allocation->political_will;
                }
            }
        }

        $highest = $totals === [] ? 0 : max($totals);
        $leaders = array_keys($totals, $highest, true);

        return [
            'totals' => $totals,
            'leaders' => $leaders,
            'tied' => count($leaders) > 1,
            'cast' => $ballots->count(),
        ];
    }

    /**
     * Resolve the vote.
     *
     * A clear winner carries. A tie is the Chair's to break and is handed
     * straight back to them rather than settled by a rule here - and the Chair
     * may only pick from the resolutions that actually tied, because breaking a
     * tie is choosing between the votes rather than overruling them.
     */
    public function resolve(
        CouncilAgendaItem $item,
        ?AgendaResolution $chairChoice = null,
        ?User $actor = null,
    ): CouncilAgendaItem {
        if ($item->isResolved()) {
            throw ValidationException::withMessages([
                'resolution' => 'That vote has already been resolved.',
            ]);
        }

        $tally = $this->tally($item);

        if ($tally['totals'] === []) {
            throw ValidationException::withMessages([
                'resolution' => 'That agenda has nothing to vote on.',
            ]);
        }

        if (! $tally['tied']) {
            $outcomeId = $tally['leaders'][0];
            $tieBroken = false;
        } else {
            if ($chairChoice === null) {
                throw ValidationException::withMessages([
                    'resolution' => 'The vote is tied. The Chair decides which resolution carries.',
                ]);
            }

            if (! in_array($chairChoice->id, $tally['leaders'], true)) {
                throw ValidationException::withMessages([
                    'resolution' => 'The Chair breaks the tie between the resolutions that tied.',
                ]);
            }

            $outcomeId = $chairChoice->id;
            $tieBroken = true;
        }

        return DB::transaction(function () use ($item, $outcomeId, $tieBroken, $actor): CouncilAgendaItem {
            $item->forceFill([
                'outcome_resolution_id' => $outcomeId,
                'tie_broken' => $tieBroken,
                'resolved_at' => Carbon::now(),
                'resolved_by_id' => $actor?->id,
            ])->save();

            $item->card->forceFill(['status' => AgendaCardStatus::Voted])->save();

            return $item;
        });
    }

    // -----------------------------------------------------------------
    // Attendance (3.1.2)
    // -----------------------------------------------------------------

    public function seatFor(CouncilSession $session, Corporation $corporation): CouncilSeat
    {
        /** @var CouncilSeat $seat */
        $seat = $session->seats()->firstOrCreate(['corporation_id' => $corporation->id]);

        return $seat;
    }

    public function markAttendance(
        CouncilSession $session,
        Corporation $corporation,
        PhaseType $phase,
        CouncilAttendance $attendance,
    ): CouncilSeat {
        $seat = $this->seatFor($session, $corporation);

        $seat->forceFill([CouncilSeat::columnFor($phase, 'attendance') => $attendance])->save();

        return $seat;
    }

    /**
     * Charge a Corporation for an empty seat.
     *
     * Control's, and only Control's. The rulebook says an absence has "a
     * negative impact on your Political Will" and names no figure, so there is
     * nothing here to derive: Control judges whether somebody was late enough
     * to matter and by how much, and this records it in the ledger with the
     * turn and phase against it.
     */
    public function applyAttendancePenalty(
        CouncilSession $session,
        Corporation $corporation,
        PhaseType $phase,
        int $amount,
        ?User $actor = null,
    ): TrackerAdjustment {
        $seat = $this->seatFor($session, $corporation);

        if ($seat->attendanceFor($phase) !== CouncilAttendance::Absent) {
            throw ValidationException::withMessages([
                'amount' => 'Mark the seat absent before charging for it.',
            ]);
        }

        if ($seat->penaltyAppliedFor($phase) !== null) {
            throw ValidationException::withMessages([
                'amount' => 'That absence has already been charged for.',
            ]);
        }

        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'A penalty has to cost something.',
            ]);
        }

        return DB::transaction(function () use ($session, $corporation, $phase, $amount, $actor, $seat): TrackerAdjustment {
            $adjustment = $this->trackers->adjust(
                $corporation,
                Tracker::PoliticalWill,
                -$amount,
                sprintf('Council: absent for turn %d\'s %s phase', $session->turn->number, $phase->label()),
                $actor,
            );

            $seat->forceFill([CouncilSeat::columnFor($phase, 'penalty_applied_at') => Carbon::now()])->save();

            return $adjustment;
        });
    }

    // -----------------------------------------------------------------

    /**
     * Rewrite a card's resolutions from a plain list of words.
     *
     * Used only while the card is being written, so it is safe to replace the
     * rows outright: nothing has voted on them yet. Once a card is in play the
     * only way to change it is an amendment, which Control signs off.
     *
     * @param  array<int, string>  $resolutions
     */
    private function replaceResolutions(AgendaCard $card, array $resolutions): void
    {
        $texts = array_values(array_filter(array_map('trim', $resolutions), fn (string $text): bool => $text !== ''));

        if (count($texts) < AgendaCard::MINIMUM_RESOLUTIONS || count($texts) > AgendaCard::MAXIMUM_RESOLUTIONS) {
            throw ValidationException::withMessages([
                'resolutions' => sprintf(
                    'An agenda has between %d and %d resolutions.',
                    AgendaCard::MINIMUM_RESOLUTIONS,
                    AgendaCard::MAXIMUM_RESOLUTIONS,
                ),
            ]);
        }

        $card->resolutions()->delete();

        foreach ($texts as $index => $text) {
            $card->resolutions()->create([
                'position' => $index + 1,
                'text' => $text,
            ]);
        }

        $card->load('resolutions');
    }

    private function assertCustom(AgendaCard $card): void
    {
        if (! $card->isCustom()) {
            throw ValidationException::withMessages([
                'status' => 'That is one of Control\'s deck cards.',
            ]);
        }
    }

    private function assertWithChair(AgendaCard $card): void
    {
        if ($card->status !== AgendaCardStatus::WithChair) {
            throw ValidationException::withMessages([
                'agenda_card_id' => 'That card is not in front of the Chair.',
            ]);
        }
    }

    private function assertAmendable(AgendaResolution $resolution): void
    {
        if ($resolution->removed_at !== null) {
            throw ValidationException::withMessages([
                'resolution' => 'That resolution has already been removed.',
            ]);
        }

        if ($resolution->pending_amendment !== null) {
            throw ValidationException::withMessages([
                'resolution' => 'That resolution already has an amendment waiting on Control.',
            ]);
        }
    }

    private function assertResolutionBounds(AgendaCard $card): void
    {
        $count = $card->votableResolutions()->count();

        if ($count < AgendaCard::MINIMUM_RESOLUTIONS || $count > AgendaCard::MAXIMUM_RESOLUTIONS) {
            throw ValidationException::withMessages([
                'resolutions' => sprintf(
                    'An agenda has between %d and %d resolutions.',
                    AgendaCard::MINIMUM_RESOLUTIONS,
                    AgendaCard::MAXIMUM_RESOLUTIONS,
                ),
            ]);
        }
    }
}
