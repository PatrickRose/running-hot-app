<?php

namespace Database\Factories;

use App\Enums\AgendaCardStatus;
use App\Models\AgendaCard;
use App\Models\Game;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AgendaCard>
 */
class AgendaCardFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'game_id' => Game::factory(),
            'title' => fake()->unique()->sentence(3),
            'body' => fake()->sentence(),
            'status' => AgendaCardStatus::Deck,
        ];
    }

    /**
     * A card with the two resolutions every agenda needs to be voted on.
     *
     * @param  array<int, string>|null  $texts
     */
    public function withResolutions(?array $texts = null): static
    {
        return $this->afterCreating(function (AgendaCard $card) use ($texts): void {
            foreach (array_values($texts ?? ['For', 'Against']) as $index => $text) {
                $card->resolutions()->create([
                    'position' => $index + 1,
                    'text' => $text,
                ]);
            }

            $card->load('resolutions');
        });
    }
}
