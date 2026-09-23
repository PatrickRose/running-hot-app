<?php

namespace Tests\Feature;

use App\Enums\CharacterRole;
use App\Enums\GameStatus;
use App\Enums\StockCertificateOption;
use App\Enums\Tracker;
use App\Models\Character;
use App\Models\ControlMember;
use App\Models\Corporation;
use App\Models\Game;
use App\Models\StockCertificate;
use App\Models\TrackerAdjustment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Stock Certificates (rulebook 3.4.3): Control hands one out, its holder cashes
 * it in or hands it on.
 *
 * The card reads "Take a half (rounded down) of the corporation income as
 * credits and reduce their income by 1, or take a quarter (rounded up) of the
 * corporation's income as credits", and that is the rule these hold the
 * application to.
 */
class StockCertificateTest extends TestCase
{
    use RefreshDatabase;

    private Game $game;

    private Corporation $dtc;

    protected function setUp(): void
    {
        parent::setUp();

        $this->game = Game::factory()->create(['status' => GameStatus::Running]);
        $this->dtc = Corporation::factory()->for($this->game)->create([
            'name' => 'Test Certificate Combine',
            'income' => 9,
            'credits' => 20,
        ]);
    }

    public function test_control_hands_a_runner_a_certificate(): void
    {
        [, $runner] = $this->runner('Wicker');

        $this->actingAs($this->control())
            ->post("/control/games/{$this->game->id}/stock-certificates", [
                'corporation_id' => $this->dtc->id,
                'character_id' => $runner->id,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        /** @var StockCertificate $certificate */
        $certificate = StockCertificate::query()->sole();

        $this->assertSame($this->dtc->id, $certificate->corporation_id);
        $this->assertSame($runner->id, $certificate->character_id);
        $this->assertFalse($certificate->isCashed());
    }

    public function test_a_player_cannot_hand_themselves_a_certificate(): void
    {
        [$user, $runner] = $this->runner('Wicker');

        $this->actingAs($user)
            ->post("/control/games/{$this->game->id}/stock-certificates", [
                'corporation_id' => $this->dtc->id,
                'character_id' => $runner->id,
            ])
            ->assertForbidden();

        $this->assertSame(0, StockCertificate::query()->count());
    }

    /**
     * Half of 9, rounded down, is 4 - and the Corporation's Income drops by 1.
     */
    public function test_cashing_for_half_pays_half_rounded_down_and_cuts_the_income(): void
    {
        [$user, $runner] = $this->runner('Wicker', credits: 2);
        $certificate = $this->issue($runner);

        $this->actingAs($user)
            ->post("/stock-certificates/{$certificate->id}/cash", ['option' => 'half'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(6, $runner->fresh()->credits);
        $this->assertSame(8, $this->dtc->fresh()->income);
        // A share of its Income, not money out of its vault.
        $this->assertSame(20, $this->dtc->fresh()->credits);

        $certificate->refresh();
        $this->assertTrue($certificate->isCashed());
        $this->assertSame(StockCertificateOption::Half, $certificate->cashed_as);
        $this->assertSame(4, $certificate->credits_paid);
        $this->assertSame($runner->id, $certificate->cashed_by_character_id);
    }

    /**
     * A quarter of 9, rounded up, is 3 - and nothing else moves.
     */
    public function test_cashing_for_a_quarter_pays_a_quarter_rounded_up_and_leaves_the_income(): void
    {
        [$user, $runner] = $this->runner('Wicker', credits: 0);
        $certificate = $this->issue($runner);

        $this->actingAs($user)
            ->post("/stock-certificates/{$certificate->id}/cash", ['option' => 'quarter'])
            ->assertSessionHasNoErrors();

        $this->assertSame(3, $runner->fresh()->credits);
        $this->assertSame(9, $this->dtc->fresh()->income);
    }

    /**
     * Every number it moves goes through the ledger, so "why did their Income
     * drop?" has an answer three turns later.
     */
    public function test_both_movements_are_in_the_ledger(): void
    {
        [$user, $runner] = $this->runner('Wicker');
        $certificate = $this->issue($runner);

        $this->actingAs($user)->post("/stock-certificates/{$certificate->id}/cash", ['option' => 'half']);

        $this->assertSame(1, TrackerAdjustment::query()
            ->where('tracker', Tracker::CharacterCredits->value)
            ->where('delta', 4)
            ->count());
        $this->assertSame(1, TrackerAdjustment::query()
            ->where('tracker', Tracker::Income->value)
            ->where('delta', -1)
            ->count());
    }

    /**
     * @return array<string, array{0: int, 1: int, 2: int}>
     */
    public static function incomes(): array
    {
        return [
            'nothing' => [0, 0, 0],
            'one' => [1, 0, 1],
            'four' => [4, 2, 1],
            'five' => [5, 2, 2],
            'ten' => [10, 5, 3],
            'negative' => [-3, 0, 0],
        ];
    }

    #[DataProvider('incomes')]
    public function test_the_arithmetic_of_both_options(int $income, int $half, int $quarter): void
    {
        $this->assertSame($half, StockCertificateOption::Half->creditsFor($income));
        $this->assertSame($quarter, StockCertificateOption::Quarter->creditsFor($income));
    }

    public function test_a_certificate_is_cashed_once(): void
    {
        [$user, $runner] = $this->runner('Wicker', credits: 0);
        $certificate = $this->issue($runner);

        $this->actingAs($user)->post("/stock-certificates/{$certificate->id}/cash", ['option' => 'quarter']);

        $this->actingAs($user)
            ->post("/stock-certificates/{$certificate->id}/cash", ['option' => 'half'])
            ->assertSessionHasErrors('certificate');

        $this->assertSame(3, $runner->fresh()->credits);
        $this->assertSame(9, $this->dtc->fresh()->income);
    }

    /**
     * Reads the Income as it is when cashed, not as it was when stolen.
     */
    public function test_the_income_is_read_when_it_is_cashed(): void
    {
        [$user, $runner] = $this->runner('Wicker', credits: 0);
        $certificate = $this->issue($runner);

        $this->dtc->update(['income' => 14]);

        $this->actingAs($user)->post("/stock-certificates/{$certificate->id}/cash", ['option' => 'half']);

        $this->assertSame(7, $runner->fresh()->credits);
        $this->assertSame(13, $this->dtc->fresh()->income);
    }

    public function test_only_the_holder_may_cash_it(): void
    {
        [, $runner] = $this->runner('Wicker');
        [$other] = $this->runner('Ghost');
        $certificate = $this->issue($runner);

        $this->actingAs($other)
            ->post("/stock-certificates/{$certificate->id}/cash", ['option' => 'half'])
            ->assertForbidden();

        $this->assertFalse($certificate->fresh()->isCashed());
    }

    public function test_a_game_off_the_clock_refuses_cashing(): void
    {
        [$user, $runner] = $this->runner('Wicker');
        $certificate = $this->issue($runner);
        $this->game->update(['status' => GameStatus::Finished]);

        $this->actingAs($user)
            ->post("/stock-certificates/{$certificate->id}/cash", ['option' => 'half'])
            ->assertForbidden();
    }

    public function test_control_cashes_one_on_a_players_behalf(): void
    {
        [, $runner] = $this->runner('Wicker', credits: 0);
        $certificate = $this->issue($runner);

        $this->actingAs($this->control())
            ->post("/stock-certificates/{$certificate->id}/cash", ['option' => 'quarter'])
            ->assertSessionHasNoErrors();

        // Paid to the holder, not to whoever pressed the button.
        $this->assertSame(3, $runner->fresh()->credits);
    }

    /**
     * 3.4.3: certificates "can be sold to Corporations". A Corporate seat has
     * no purse of its own, so what it cashes lands in its Corporation's.
     */
    public function test_a_corporate_holder_is_paid_into_their_corporation(): void
    {
        $gordon = Corporation::factory()->for($this->game)->create([
            'name' => 'Test Buyer Holdings',
            'credits' => 10,
        ]);
        $user = User::factory()->create();
        $ceo = Character::factory()->corporate(CharacterRole::Ceo, $gordon)->create([
            'game_id' => $this->game->id,
            'user_id' => $user->id,
        ]);
        $certificate = $this->issue($ceo);

        $this->actingAs($user)
            ->post("/stock-certificates/{$certificate->id}/cash", ['option' => 'half'])
            ->assertSessionHasNoErrors();

        $this->assertSame(14, $gordon->fresh()->credits);
        $this->assertSame(8, $this->dtc->fresh()->income);
    }

    public function test_a_holder_hands_it_on(): void
    {
        [$user, $runner] = $this->runner('Wicker');
        [, $ghost] = $this->runner('Ghost');
        $certificate = $this->issue($runner);

        $this->actingAs($user)
            ->post("/stock-certificates/{$certificate->id}/give", ['to_character_id' => $ghost->id])
            ->assertSessionHasNoErrors();

        $this->assertSame($ghost->id, $certificate->fresh()->character_id);

        // And it is no longer the giver's to cash.
        $this->actingAs($user)
            ->post("/stock-certificates/{$certificate->id}/cash", ['option' => 'half'])
            ->assertForbidden();
    }

    public function test_a_cashed_certificate_cannot_be_handed_on(): void
    {
        [$user, $runner] = $this->runner('Wicker');
        [, $ghost] = $this->runner('Ghost');
        $certificate = $this->issue($runner);

        $this->actingAs($this->control())
            ->post("/stock-certificates/{$certificate->id}/cash", ['option' => 'quarter']);

        $this->actingAs($this->control())
            ->post("/stock-certificates/{$certificate->id}/give", ['to_character_id' => $ghost->id])
            ->assertSessionHasErrors('certificate');

        $this->assertSame($runner->id, $certificate->fresh()->character_id);
    }

    public function test_control_takes_back_an_uncashed_certificate_and_not_a_cashed_one(): void
    {
        [, $runner] = $this->runner('Wicker');
        $mistake = $this->issue($runner);
        $spent = $this->issue($runner);
        $control = $this->control();

        $this->actingAs($control)->post("/stock-certificates/{$spent->id}/cash", ['option' => 'quarter']);

        $this->actingAs($control)
            ->delete("/control/games/{$this->game->id}/stock-certificates/{$mistake->id}")
            ->assertSessionHasNoErrors();

        $this->actingAs($control)
            ->delete("/control/games/{$this->game->id}/stock-certificates/{$spent->id}")
            ->assertSessionHasErrors('certificate');

        $this->assertNull($mistake->fresh());
        $this->assertNotNull($spent->fresh());
    }

    /**
     * A player reads the certificates in their own hands, with what each
     * option would pay; nobody else's reaches the browser.
     */
    public function test_the_equipment_page_shows_a_player_their_own_certificates(): void
    {
        [$user, $runner] = $this->runner('Wicker');
        [, $ghost] = $this->runner('Ghost');
        $this->issue($runner);
        $this->issue($ghost);

        $this->actingAs($user)
            ->get('/equipment')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('certificates', 1)
                ->where('certificates.0.holder_character_id', $runner->id)
                ->where('certificates.0.text', StockCertificate::TEXT)
                ->where('certificates.0.options.0.value', 'half')
                ->where('certificates.0.options.0.credits', 4)
                ->where('certificates.0.options.0.income_reduction', 1)
                ->where('certificates.0.options.1.value', 'quarter')
                ->where('certificates.0.options.1.credits', 3)
                ->where('certificates.0.can_cash', true)
                ->where('certificates.0.can_give', true));
    }

    public function test_controls_card_page_lists_every_certificate(): void
    {
        [, $runner] = $this->runner('Wicker');
        [, $ghost] = $this->runner('Ghost');
        $this->issue($runner);
        $this->issue($ghost);

        $this->actingAs($this->control())
            ->get("/control/games/{$this->game->id}/cards")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('stockCertificates', 2)
                ->where('stockCertificates.0.can_cash', true));
    }

    /**
     * @return array{0: User, 1: Character}
     */
    private function runner(string $name, int $credits = 0): array
    {
        $user = User::factory()->create();

        $character = Character::factory()->runner()->create([
            'game_id' => $this->game->id,
            'user_id' => $user->id,
            'name' => $name,
            'credits' => $credits,
        ]);

        return [$user, $character];
    }

    private function issue(Character $holder): StockCertificate
    {
        return StockCertificate::query()->create([
            'game_id' => $this->game->id,
            'corporation_id' => $this->dtc->id,
            'character_id' => $holder->id,
        ]);
    }

    private function control(): User
    {
        $user = User::factory()->create();
        ControlMember::factory()->for($this->game)->create(['user_id' => $user->id]);

        return $user;
    }
}
