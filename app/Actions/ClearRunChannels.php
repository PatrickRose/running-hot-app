<?php

namespace App\Actions;

use App\Enums\DiscordResourceKind;
use App\Models\DiscordResource;
use App\Models\Facility;
use App\Models\Run;
use App\Models\Turn;
use App\Services\Discord\DiscordApi;
use App\Support\Discord\GuildBlueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Clears out the text channel of every Facility that was run against this turn.
 *
 * A Facility's channels are permanent and every Run against it happens in the
 * same pair, which is what makes this necessary rather than tidy: a group
 * hitting Burngreave Vault on turn 4 would otherwise open the channel and read
 * turn 3's group working out exactly which Protection Cards were in the stack,
 * in what order, and what each of them cost to get past. Rulebook 3.4.2 makes
 * the contents of a Facility Secret and reconnaissance is what you spend an
 * action on to find out - so last turn's transcript sitting in the room is a
 * free recon action for everybody who comes after.
 *
 * **What is worth reading is kept somewhere better.** The run's own log is
 * `run_events` - every card, roll, consequence and departure, with who did it
 * and when - and it is on the run screen and Control's panel for the rest of
 * the game. Nothing here touches it. What is deleted is the conversation
 * around it, which is the half that leaks.
 *
 * **A pinned message survives**, which is the override: Control pins whatever
 * should outlive the turn and this leaves it exactly where it is. Same instinct
 * as everything else here - the organisers must be able to say otherwise.
 *
 * **Only the Facilities that were actually run against.** Sweeping every
 * channel in the guild every turn would be a pile of requests against a rate
 * limit Discord enforces hard, for channels where nothing was said.
 *
 * Voice channels have nothing to clear, so only the text half is touched.
 */
class ClearRunChannels
{
    /**
     * Discord takes between 2 and 100 message ids in a bulk delete.
     */
    public const MAX_BULK_DELETE = 100;

    /**
     * How many pages of history to read before giving up.
     *
     * A run is a ten-minute conversation between at most a handful of people,
     * so a thousand messages is already far more than one produces. The cap is
     * here so that a channel somebody has been chatting in all evening cannot
     * turn one turn's tidy-up into an unbounded run of requests.
     */
    public const MAX_PAGES = 10;

    /**
     * Discord refuses to bulk-delete anything older than two weeks.
     *
     * Well outside a single evening's game, so in practice every message goes
     * through the bulk path - but a long-running test server is exactly where
     * this would be hit, and a 400 there would abandon the rest of the sweep.
     */
    public const BULK_DELETE_MAX_AGE_DAYS = 14;

    public function __construct(private readonly DiscordApi $api) {}

    /**
     * Clear the channel of every Facility run against during this turn.
     *
     * @return array<string, int> messages deleted, keyed by Facility name
     */
    public function handle(Turn $turn): array
    {
        $game = $turn->game;

        if (blank($game->discord_guild_id) || ! $this->api->isConfigured()) {
            return [];
        }

        $cleared = [];

        foreach ($this->facilitiesRunAgainst($turn) as $facility) {
            $channelId = $this->textChannelId($facility);

            if ($channelId === null) {
                continue;
            }

            $deleted = $this->clear($channelId, $facility);

            if ($deleted > 0) {
                $cleared[$facility->name] = $deleted;
            }
        }

        if ($cleared !== []) {
            Log::info('Cleared the run channels for a finished turn.', [
                'game_id' => $game->id,
                'turn' => $turn->number,
                'cleared' => $cleared,
            ]);
        }

        return $cleared;
    }

    /**
     * Every Facility a group went into this turn.
     *
     * Read off the runs rather than off the roster, and only the ones that
     * actually started: a run submitted and never begun put nobody in the
     * channel, so there is nothing in it of theirs to clear.
     *
     * @return Collection<int, Facility>
     */
    private function facilitiesRunAgainst(Turn $turn): mixed
    {
        return Run::query()
            ->where('turn_id', $turn->id)
            ->whereNotNull('started_at')
            ->with('facility')
            ->get()
            ->map(fn (Run $run): Facility => $run->facility)
            ->unique('id')
            ->values();
    }

    /**
     * The Facility's text channel, if the application has one on record.
     *
     * A Facility with no channels is normal rather than an error - a Discord
     * outage during a requisition leaves one without them, and the Control
     * panel already offers to build the pair.
     */
    private function textChannelId(Facility $facility): ?string
    {
        /** @var DiscordResource|null $resource */
        $resource = $facility->game->discordResources()
            ->where('key', GuildBlueprint::facilityChannelKey($facility, 'text'))
            ->where('kind', DiscordResourceKind::TextChannel)
            ->first();

        return $resource?->discord_id;
    }

    /**
     * Delete what is in one channel, oldest page last.
     *
     * @return int how many messages went
     */
    private function clear(string $channelId, Facility $facility): int
    {
        $reason = sprintf('Running Hot: clearing %s after the turn', $facility->name);
        $deleted = 0;
        $before = null;

        for ($page = 0; $page < self::MAX_PAGES; $page++) {
            $messages = $this->api->channelMessages($channelId, self::MAX_BULK_DELETE, $before);

            if ($messages === []) {
                break;
            }

            // Paged by the oldest id of this answer, which has to be taken
            // before anything is deleted: a pinned message is the only thing
            // still there afterwards, and paging from a deleted id returns
            // nothing at all.
            $before = (string) ($messages[array_key_last($messages)]['id'] ?? '');

            $deleted += $this->deletePage($channelId, $messages, $reason);

            if (count($messages) < self::MAX_BULK_DELETE || $before === '') {
                break;
            }
        }

        return $deleted;
    }

    /**
     * @param  array<int, array<string, mixed>>  $messages
     */
    private function deletePage(string $channelId, array $messages, string $reason): int
    {
        $recent = [];
        $old = [];
        $cutoff = Carbon::now()->subDays(self::BULK_DELETE_MAX_AGE_DAYS);

        foreach ($messages as $message) {
            // Control's own call, left exactly where it is.
            if (($message['pinned'] ?? false) === true) {
                continue;
            }

            $id = (string) ($message['id'] ?? '');

            if ($id === '') {
                continue;
            }

            $timestamp = $message['timestamp'] ?? null;
            $tooOld = is_string($timestamp) && Carbon::parse($timestamp)->lessThan($cutoff);

            if ($tooOld) {
                $old[] = $id;
            } else {
                $recent[] = $id;
            }
        }

        $deleted = 0;

        // Discord refuses a bulk delete of one, so a lone message goes the
        // single-message way rather than being left behind.
        if (count($recent) === 1) {
            $old[] = $recent[0];
            $recent = [];
        }

        if ($recent !== []) {
            $this->api->bulkDeleteMessages($channelId, $recent, $reason);
            $deleted += count($recent);
        }

        foreach ($old as $id) {
            $this->api->deleteMessage($channelId, $id, $reason);
            $deleted++;
        }

        return $deleted;
    }
}
