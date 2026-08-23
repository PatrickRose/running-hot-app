<?php

namespace App\Services;

use App\Jobs\SendDiscordAnnouncement;
use App\Models\Game;
use App\Models\Phase;

/**
 * Announces game events to Discord, which is where the players actually live.
 */
class DiscordAnnouncer
{
    public function phaseStarted(Phase $phase): void
    {
        $game = $phase->game();
        $minutes = (int) ceil($phase->remainingSeconds() / 60);

        $this->send($game, sprintf(
            '**Turn %d — %s phase has begun.** You have %d minute%s.',
            $phase->turn->number,
            $phase->type->label(),
            $minutes,
            $minutes === 1 ? '' : 's',
        ));
    }

    public function phaseEnded(Phase $phase): void
    {
        $this->send($phase->game(), sprintf(
            '**Turn %d — %s phase is over.** Finish what you are doing and stand by.',
            $phase->turn->number,
            $phase->type->label(),
        ));
    }

    public function phasePaused(Phase $phase): void
    {
        $this->send($phase->game(), sprintf(
            '⏸️ **Turn %d — %s phase is paused by Control.** Hold where you are.',
            $phase->turn->number,
            $phase->type->label(),
        ));
    }

    public function phaseResumed(Phase $phase): void
    {
        $minutes = (int) ceil($phase->remainingSeconds() / 60);

        $this->send($phase->game(), sprintf(
            '▶️ **Turn %d — %s phase resumes.** Roughly %d minute%s left.',
            $phase->turn->number,
            $phase->type->label(),
            $minutes,
            $minutes === 1 ? '' : 's',
        ));
    }

    public function phaseExtended(Phase $phase, int $seconds): void
    {
        $minutes = (int) ceil($seconds / 60);

        $this->send($phase->game(), sprintf(
            '⏱️ **Control has added %d minute%s to the %s phase.**',
            $minutes,
            $minutes === 1 ? '' : 's',
            $phase->type->label(),
        ));
    }

    public function gameFinished(Game $game): void
    {
        $this->send($game, '🏁 **The game is over.** Thank you all for playing Running Hot.');
    }

    public function send(Game $game, string $content): void
    {
        // A game always has its own webhook, so there is nothing to fall back to
        // and no silent no-op path.
        SendDiscordAnnouncement::dispatch($game->discord_webhook_url, $content);
    }
}
