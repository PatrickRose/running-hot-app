<?php

namespace App\Support;

use App\Enums\AgendaCardStatus;
use App\Enums\AgendaItemSource;
use App\Enums\CharacterRole;
use App\Enums\PhaseStatus;
use App\Models\AgendaCard;
use App\Models\AgendaResolution;
use App\Models\Character;
use App\Models\Corporation;
use App\Models\CouncilAgendaItem;
use App\Models\CouncilBallot;
use App\Models\CouncilSeat;
use App\Models\CouncilSession;
use App\Models\Game;
use App\Models\User;
use App\Policies\CouncilSessionPolicy;
use App\Services\CouncilService;

/**
 * The Council as each person at it may see it (rulebook 3.1).
 *
 * The whole of this class is about who sees what, because the Council has one
 * genuine secret in it. A vote the Chair has declared secret is withheld from
 * the other players and *not* from the Chair - 3.1.2 has the Chair receiving
 * the individual breakdowns either way and passing them on as they see fit. So
 * "hidden" here always means hidden from the players, and the Chair's view is
 * built separately rather than by hiding less of the same payload.
 *
 * A vote in progress is the other case worth stating: everyone can see which
 * Corporations have handed a slip to the Chair, because at the table you watch
 * them do it, and nobody but the Chair can see what is written on it.
 */
class CouncilPresenter
{
    public function __construct(private readonly CouncilService $council) {}

    /**
     * The Council page, for whoever is looking at it.
     *
     * @return array<string, mixed>
     */
    public function forPlayer(Game $game, ?User $user): array
    {
        $turn = $game->currentTurn();
        $session = $turn?->councilSession()->first();

        $viewer = $this->viewer($game, $session, $user);

        // What the Chair may see, which Control may see too. Read from the
        // ability rather than from the seat, because this is a question about
        // permission rather than about identity.
        $privileged = $viewer['can_chair'] || $viewer['is_control'];

        return [
            'turn' => $turn?->number,
            'session' => $session === null ? null : $this->session($game, $session),
            'viewer' => $viewer,
            // What Control has handed the Chair, before two of them are read
            // out. Nobody else has seen these, so nobody else is shown them.
            'hand' => $privileged && $session !== null
                ? $this->cardsInSession($session, AgendaCardStatus::InHand)
                : [],
            'items' => $session === null ? [] : $this->items($session, $viewer, $privileged),
            'with_chair' => $privileged
                ? $this->cards($game, AgendaCardStatus::WithChair)
                : [],
            'important' => $privileged
                ? $this->cards($game, AgendaCardStatus::Important)
                : [],
            // Control's alone, and pointedly not the Chair's: a card sitting
            // with Control has not been given to the Chair yet, and might never
            // be - the author sees the remarks first and may keep it back
            // (3.1.3). Showing the Chair would be handing it over early.
            //
            // It is here rather than only on the Control panel because a player
            // is told their card is with Control, and the Council Chamber is
            // where they and Control both go to look for it.
            'with_control' => $viewer['is_control']
                ? $this->cards($game, AgendaCardStatus::WithControl)
                : [],
            'my_cards' => $this->myCards($game, $user),
        ];
    }

    /**
     * What Control adds to that: the deck, the cards waiting on its remarks,
     * the amendments waiting on its sign-off, and the register.
     *
     * @return array<string, mixed>
     */
    public function forControl(Game $game): array
    {
        $turn = $game->currentTurn();
        $session = $turn?->councilSession()->first();

        return [
            'deck' => $this->cards($game, AgendaCardStatus::Deck),
            'with_control' => $this->cards($game, AgendaCardStatus::WithControl),
            'amendments' => $this->pendingAmendments($game),
            'rotation' => $this->council->rotation($game)
                ->map(fn (Corporation $corporation): array => [
                    'id' => $corporation->id,
                    ...FactionBadge::for($corporation->name),
                    'chair_order' => $corporation->council_chair_order,
                    'is_chair' => $session?->chair_corporation_id === $corporation->id,
                ])->all(),
            'seats' => $session === null ? [] : $this->seats($game, $session),
            'absence_penalty' => (int) config('running_hot.council.absence_penalty'),
            'recess_seconds' => $game->council_recess_seconds,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function session(Game $game, CouncilSession $session): array
    {
        // A paused phase pauses the recess with it, exactly as it pauses the
        // phase clock. The stored moment is put right when Control resumes.
        $phase = $game->currentPhase();
        $reference = $phase?->status === PhaseStatus::Paused ? $phase->paused_at : null;

        return [
            'id' => $session->id,
            'chair' => $session->chair === null ? null : [
                'id' => $session->chair->id,
                ...FactionBadge::for($session->chair->name),
            ],
            'recess_at' => $session->recess_at?->toIso8601String(),
            'recess_seconds_remaining' => $session->recessSecondsRemaining($reference),
            'in_recess' => $session->isInRecess($reference),
            'paused' => $reference !== null,
            'has_handed' => $session->hasHandedOver(),
            'chair_has_chosen' => $this->council->chairHasChosen($session),
            'tabled_count' => $this->council->tabledCount($session),
            'maximum_items' => CouncilSession::MAXIMUM_ITEMS,
            'cards_handed' => CouncilSession::CARDS_HANDED,
            'cards_kept' => CouncilSession::CARDS_KEPT,
            'can_promote' => $session->items()->where('source', AgendaItemSource::Promoted)->doesntExist(),
        ];
    }

    /**
     * Who the viewer is at this Council, which is what every other decision
     * here is made from.
     *
     * @return array<string, mixed>
     */
    private function viewer(Game $game, ?CouncilSession $session, ?User $user): array
    {
        $blank = [
            'is_control' => false,
            'is_chair' => false,
            'can_chair' => false,
            'can_vote' => false,
            'can_submit_agenda' => false,
            'corporation' => null,
            'character_id' => null,
        ];

        if ($user === null) {
            return $blank;
        }

        $isControl = $user->isControlFor($game);

        /** @var Character|null $ceo */
        $ceo = $game->characters()
            ->where('user_id', $user->id)
            ->where('role', CharacterRole::Ceo)
            ->whereNotNull('corporation_id')
            ->with('corporation')
            ->first();

        // Any character will do to write a custom agenda: 3.1.3 gives blank
        // cards to players rather than to CEOs.
        $anyCharacter = $ceo ?? $game->characters()
            ->where('user_id', $user->id)
            ->first();

        // Who you are and what you may do are different questions, and the
        // Gate can only answer the second: CouncilSessionPolicy::before() hands
        // Control every ability at the Council, so asking it "are you the
        // Chair" of Control gets a yes - and the page then tells Control it is
        // Augmented Nucleotech.
        //
        // So the seat is asked of the policy method directly, under the
        // override rather than through it, and the ability is asked of the
        // Gate. Control may do everything the Chair can and is still not the
        // Chair, which is exactly what the page should say.
        $seats = app(CouncilSessionPolicy::class);

        return [
            'is_control' => $isControl,
            'is_chair' => $session !== null && $seats->chair($user, $session),
            'can_chair' => $session !== null && $user->can('chair', $session),
            'can_vote' => $session !== null && $seats->vote($user, $session),
            'can_submit_agenda' => $anyCharacter !== null && $user->can('create', [AgendaCard::class, $game]),
            'corporation' => $ceo?->corporation === null ? null : [
                'id' => $ceo->corporation->id,
                ...FactionBadge::for($ceo->corporation->name),
                'political_will' => $ceo->corporation->political_will,
            ],
            'character_id' => $anyCharacter?->id,
        ];
    }

    /**
     * @param  array<string, mixed>  $viewer
     * @return array<int, array<string, mixed>>
     */
    private function items(CouncilSession $session, array $viewer, bool $privileged): array
    {
        $ownCorporationId = $viewer['corporation']['id'] ?? null;

        return $session->items()
            ->whereHas('card', fn ($query) => $query->whereIn('status', [
                AgendaCardStatus::Tabled->value,
                AgendaCardStatus::Voted->value,
            ]))
            ->with(['card.resolutions', 'card.author', 'outcome', 'ballots.allocations', 'ballots.corporation'])
            ->orderBy('id')
            ->get()
            ->map(fn (CouncilAgendaItem $item): array => $this->item($item, $ownCorporationId, $privileged, (bool) $viewer['can_vote']))
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function item(CouncilAgendaItem $item, ?int $ownCorporationId, bool $privileged, bool $canVote): array
    {
        $live = $item->ballots->filter(fn (CouncilBallot $ballot): bool => ! $ballot->wasReturned());
        $tally = $this->council->tally($item);

        // The breakdown is the secret. It goes to the Chair and to Control
        // always, and to everybody else only once a vote that was never
        // declared secret has been resolved - which is the Chair reading the
        // result out, and is the default of 3.1.2.
        $breakdownVisible = $privileged || ($item->isResolved() && ! $item->secret);

        $yours = $ownCorporationId === null
            ? null
            : $live->firstWhere('corporation_id', $ownCorporationId);

        return [
            'id' => $item->id,
            'source' => $item->source->value,
            'source_label' => $item->source->label(),
            'secret' => $item->secret,
            'resolved' => $item->isResolved(),
            'resolved_at' => $item->resolved_at?->toIso8601String(),
            'tie_broken' => $item->tie_broken,
            'card' => $this->card($item->card),
            'outcome' => $item->outcome === null ? null : [
                'resolution_id' => $item->outcome->id,
                'text' => $item->outcome->text,
            ],
            // Who has handed a slip to the Chair. Public: you can see somebody
            // vote even when you cannot see what they wrote.
            'submitted' => $live
                ->map(fn (CouncilBallot $ballot): array => [
                    // The id, so the Chair can hand this one slip back. It
                    // says nothing about what is written on it, and the route
                    // that acts on it is the Chair's alone.
                    'ballot_id' => $ballot->id,
                    'corporation_id' => $ballot->corporation_id,
                    ...FactionBadge::for($ballot->corporation->name),
                    'submitted_at' => $ballot->submitted_at->toIso8601String(),
                ])->values()->all(),
            'totals' => $breakdownVisible ? $tally['totals'] : null,
            'tied' => $breakdownVisible ? $tally['tied'] : null,
            'breakdown' => $breakdownVisible
                ? $live->map(fn (CouncilBallot $ballot): array => [
                    'corporation_id' => $ballot->corporation_id,
                    ...FactionBadge::for($ballot->corporation->name),
                    'allocations' => $ballot->allocations
                        ->mapWithKeys(fn ($allocation): array => [
                            $allocation->agenda_resolution_id => $allocation->political_will,
                        ])->all(),
                ])->values()->all()
                : null,
            // Your own vote is never hidden from you.
            'your_ballot' => $yours === null ? null : [
                'id' => $yours->id,
                'submitted_at' => $yours->submitted_at->toIso8601String(),
                'allocations' => $yours->allocations
                    ->mapWithKeys(fn ($allocation): array => [
                        $allocation->agenda_resolution_id => $allocation->political_will,
                    ])->all(),
            ],
            'can_vote' => $canVote && ! $item->isResolved() && $yours === null,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function cards(Game $game, AgendaCardStatus $status): array
    {
        return $game->agendaCards()
            ->where('status', $status)
            ->with(['resolutions', 'author'])
            ->orderBy('id')
            ->get()
            ->map(fn (AgendaCard $card): array => $this->card($card))
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function cardsInSession(CouncilSession $session, AgendaCardStatus $status): array
    {
        return AgendaCard::query()
            ->whereIn('id', $session->items()->pluck('agenda_card_id'))
            ->where('status', $status)
            ->with(['resolutions', 'author'])
            ->orderBy('id')
            ->get()
            ->map(fn (AgendaCard $card): array => $this->card($card))
            ->all();
    }

    /**
     * The viewer's own custom agendas, wherever they have got to.
     *
     * @return array<int, array<string, mixed>>
     */
    private function myCards(Game $game, ?User $user): array
    {
        if ($user === null) {
            return [];
        }

        $characterIds = $game->characters()->where('user_id', $user->id)->pluck('id');

        if ($characterIds->isEmpty()) {
            return [];
        }

        return $game->agendaCards()
            ->whereIn('submitted_by_character_id', $characterIds)
            ->with(['resolutions', 'author'])
            ->orderByDesc('id')
            ->get()
            ->map(fn (AgendaCard $card): array => $this->card($card))
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function card(AgendaCard $card): array
    {
        return [
            'id' => $card->id,
            'title' => $card->title,
            'body' => $card->body,
            'control_note' => $card->control_note,
            'status' => $card->status->value,
            'status_label' => $card->status->label(),
            'is_custom' => $card->isCustom(),
            'author' => $card->author?->name,
            'editable_by_author' => $card->status->isEditableByAuthor(),
            'resolutions' => $card->resolutions
                ->map(fn (AgendaResolution $resolution): array => $this->resolution($resolution))
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function resolution(AgendaResolution $resolution): array
    {
        return [
            'id' => $resolution->id,
            'position' => $resolution->position,
            'text' => $resolution->text,
            'votable' => $resolution->isVotable(),
            'removed' => $resolution->removed_at !== null,
            'pending_amendment' => $resolution->pending_amendment?->value,
            'pending_amendment_label' => $resolution->pending_amendment?->label(),
            'pending_text' => $resolution->pending_text,
        ];
    }

    /**
     * Every amendment waiting on Council Control's sign-off (3.1.4).
     *
     * @return array<int, array<string, mixed>>
     */
    private function pendingAmendments(Game $game): array
    {
        return AgendaResolution::query()
            ->whereNotNull('pending_amendment')
            ->whereIn('agenda_card_id', $game->agendaCards()->select('id'))
            ->with(['card', 'proposedBy'])
            ->orderBy('id')
            ->get()
            ->map(fn (AgendaResolution $resolution): array => [
                ...$this->resolution($resolution),
                'card_id' => $resolution->agenda_card_id,
                'card_title' => $resolution->card->title,
                'proposed_by' => $resolution->proposedBy?->name,
            ])->all();
    }

    /**
     * The register: who sat, and what their absence has cost.
     *
     * @return array<int, array<string, mixed>>
     */
    private function seats(Game $game, CouncilSession $session): array
    {
        $seats = $session->seats()->get()->keyBy('corporation_id');

        return $game->corporations()
            ->orderBy('name')
            ->get()
            ->map(function (Corporation $corporation) use ($seats): array {
                /** @var CouncilSeat|null $seat */
                $seat = $seats->get($corporation->id);

                return [
                    'corporation_id' => $corporation->id,
                    ...FactionBadge::for($corporation->name),
                    'political_will' => $corporation->political_will,
                    'setup_attendance' => $seat?->setup_attendance->value ?? 'unknown',
                    'action_attendance' => $seat?->action_attendance->value ?? 'unknown',
                    'setup_penalty_applied' => $seat?->setup_penalty_applied_at !== null,
                    'action_penalty_applied' => $seat?->action_penalty_applied_at !== null,
                ];
            })->all();
    }
}
