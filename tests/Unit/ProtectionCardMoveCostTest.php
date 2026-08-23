<?php

namespace Tests\Unit;

use App\Services\FacilityDefenceService;
use App\Services\TrackerService;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The reorder cost is "1 Credit for each Protection Card you wish to move"
 * (rulebook 3.3.4), which is the cards left over once the longest run that
 * keeps its relative order stays put.
 */
class ProtectionCardMoveCostTest extends TestCase
{
    private function defence(): FacilityDefenceService
    {
        return new FacilityDefenceService(new TrackerService);
    }

    /**
     * @param  array<int, int>  $before
     * @param  array<int, int>  $after
     */
    #[DataProvider('orders')]
    public function test_it_counts_the_cards_that_have_to_move(array $before, array $after, int $expected): void
    {
        $this->assertSame($expected, $this->defence()->moveCost($before, $after));
    }

    /**
     * @return array<string, array{array<int, int>, array<int, int>, int}>
     */
    public static function orders(): array
    {
        return [
            'unchanged' => [[1, 2, 3], [1, 2, 3], 0],
            // The rulebook's worked example: A, B, C to B, C, A moves only A.
            'A to the end' => [[1, 2, 3], [2, 3, 1], 1],
            // ...and A, B, C to C, B, A moves both A and C around B.
            'reversed' => [[1, 2, 3], [3, 2, 1], 2],
            'adjacent swap' => [[1, 2, 3], [2, 1, 3], 1],
            'last to the front' => [[1, 2, 3], [3, 1, 2], 1],
            'single card' => [[1], [1], 0],
            'empty stack' => [[], [], 0],
            'four reversed' => [[1, 2, 3, 4], [4, 3, 2, 1], 3],
            'four rotated' => [[1, 2, 3, 4], [2, 3, 4, 1], 1],
        ];
    }
}
