<?php

namespace Tests\Feature;

use App\Actions\SeedAgendaCards;
use App\Enums\AgendaCardStatus;
use App\Enums\CharacterRole;
use App\Models\Character;
use App\Models\Game;
use App\Services\CouncilService;
use App\Support\AgendaCardBlueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The agenda deck a new game opens with (rulebook 3.1.1).
 */
class AgendaDeckSeedingTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_new_game_opens_with_the_games_own_deck(): void
    {
        $game = Game::factory()->create();

        $deck = $game->agendaCards()->where('status', AgendaCardStatus::Deck)->get();

        $this->assertCount(count(AgendaCardBlueprint::defaults()), $deck);

        $privacy = $deck->firstWhere('title', 'Privacy');

        $this->assertNotNull($privacy);
        $this->assertCount(3, $privacy->resolutions);
        $this->assertSame('Increase privacy protections', $privacy->resolutions->first()->text);
        // Control's, so there is no player to hand it back to.
        $this->assertNull($privacy->submitted_by_character_id);
    }

    public function test_seeding_again_adds_nothing_and_overwrites_nothing(): void
    {
        $game = Game::factory()->create();

        $privacy = $game->agendaCards()->where('title', 'Privacy')->sole();

        // As though the Chair had amended it and Control had signed it off.
        app(CouncilService::class)->rewriteCard($privacy, 'Privacy', null, ['Ban cameras', 'Keep cameras']);

        $created = app(SeedAgendaCards::class)->handle($game->fresh());

        $this->assertSame([], $created);
        $this->assertCount(count(AgendaCardBlueprint::defaults()), $game->agendaCards()->get());
        $this->assertCount(2, $privacy->fresh(['resolutions'])->resolutions);
    }

    /**
     * A player is perfectly likely to write an agenda called Privacy (3.1.3).
     * Theirs is not a copy of Control's and must not stand in for one.
     */
    public function test_a_players_card_of_the_same_name_does_not_stop_the_deck_seeding(): void
    {
        $game = Game::factory()->create();

        $character = Character::factory()->for($game)->create(['role' => CharacterRole::Runner]);

        $council = app(CouncilService::class);
        $council->draftCustomCard($character, 'Privacy', null, ['Mine', 'Not mine']);

        $created = app(SeedAgendaCards::class)->handle($game->fresh());

        // Nothing to add: Control's Privacy is already there, and the player's
        // is a different card that happens to share a name.
        $this->assertSame([], $created);
        $this->assertCount(2, $game->agendaCards()->where('title', 'Privacy')->get());

        $game->agendaCards()->where('title', 'Privacy')->get()->each(function ($card) {
            $this->assertSame(
                $card->submitted_by_character_id === null
                    ? AgendaCardStatus::Deck
                    : AgendaCardStatus::Draft,
                $card->status,
            );
        });
    }

    public function test_a_deck_card_that_control_deleted_comes_back_on_a_reseed(): void
    {
        $game = Game::factory()->create();

        $game->agendaCards()->where('title', 'Taxation')->delete();

        $created = app(SeedAgendaCards::class)->handle($game->fresh());

        $this->assertCount(1, $created);
        $this->assertSame('Taxation', $created[0]->title);
        $this->assertCount(4, $created[0]->resolutions);
    }
}
