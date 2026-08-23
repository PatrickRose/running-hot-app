<?php

namespace App\Models;

use App\Enums\DiscordResourceKind;
use Database\Factories\DiscordResourceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One thing the application has created inside a game's Discord guild.
 *
 * @property int $id
 * @property int $game_id
 * @property DiscordResourceKind $kind
 * @property string $key
 * @property string $discord_id
 * @property string $name
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['game_id', 'kind', 'key', 'discord_id', 'name'])]
class DiscordResource extends Model
{
    /** @use HasFactory<DiscordResourceFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => DiscordResourceKind::class,
        ];
    }

    /** @return BelongsTo<Game, $this> */
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }
}
