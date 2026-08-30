<?php

use App\Enums\RunnerSkill;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('protection_card_types', function (Blueprint $table) {
            // The code printed on the card (PS003, PR047, PX011). It is what
            // identifies a card in the game's own card list, and how its artwork
            // is found. Nullable because a card Control invents mid-game has
            // neither, and is shown as its text instead.
            $table->string('code')->nullable()->after('game_id');

            // The challenge as the card prints it. It replaces a skill column
            // and a strength column, which between them could not hold what the
            // real cards say: "Brute/Hack (2)" lets the Runners choose the
            // skill, "Hack (4+N) - where N is the number of cards underneath
            // this" is not known until the card is met, and "Hack (4), followed
            // by Brute (4)" is two challenges on one card. Nothing computes a
            // dice pool from it yet, so the words are what the application holds
            // and the table converts.
            $table->text('challenge')->nullable()->after('kind');
        });

        $this->carryChallengesAcross();

        Schema::table('protection_card_types', function (Blueprint $table) {
            $table->dropColumn(['challenge_skill', 'challenge_strength']);
        });

        Schema::table('protection_card_types', function (Blueprint $table) {
            // Every row has been given a challenge above, so requiring one now
            // cannot reject an existing catalogue.
            $table->text('challenge')->nullable(false)->change();

            // A card nobody can buy yet is not a card that is free, so an
            // unpriced card holds null rather than falling back to zero.
            $table->unsignedInteger('cost')->nullable()->change();
        });

        Schema::table('protection_card_types', function (Blueprint $table) {
            // Titles are not unique: Doppleganger is PX011 in the physical stack
            // and PX012 in the cyber one, and they are two different cards. The
            // code takes over as the thing that must not repeat.
            //
            // The one-copy-per-Facility rule of 3.3.4 is unaffected - it is
            // enforced on the card type rather than on the title, so a Facility
            // may hold both Dopplegangers and still only one copy of each.
            $table->dropUnique('protection_card_types_game_id_name_unique');
            $table->unique(['game_id', 'code']);
            $table->index(['game_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::table('protection_card_types', function (Blueprint $table) {
            $table->dropUnique(['game_id', 'code']);
            $table->dropIndex(['game_id', 'name']);
            $table->unique(['game_id', 'name']);

            $table->string('challenge_skill')->default(RunnerSkill::Brawn->value);
            $table->unsignedInteger('challenge_strength')->default(1);
        });

        Schema::table('protection_card_types', function (Blueprint $table) {
            $table->unsignedInteger('cost')->default(0)->change();
            $table->dropColumn(['code', 'challenge']);
        });
    }

    /**
     * Write the old skill and strength columns out as the sentence a card would
     * have printed, so a catalogue Control has already edited keeps its
     * challenges.
     *
     * Done row by row in PHP rather than in one UPDATE, because concatenating
     * two columns is spelled differently on every database this could run on.
     */
    private function carryChallengesAcross(): void
    {
        DB::table('protection_card_types')
            ->select('id', 'challenge_skill', 'challenge_strength')
            ->orderBy('id')
            ->each(function (object $card): void {
                $skill = RunnerSkill::tryFrom((string) $card->challenge_skill);

                DB::table('protection_card_types')
                    ->where('id', $card->id)
                    ->update([
                        'challenge' => sprintf(
                            '%s (%d)',
                            // The cards print "Brute" where this application
                            // says Brawn (see App\Enums\Tracker).
                            $skill === RunnerSkill::Hack ? 'Hack' : 'Brute',
                            (int) $card->challenge_strength,
                        ),
                    ]);
            });
    }
};
