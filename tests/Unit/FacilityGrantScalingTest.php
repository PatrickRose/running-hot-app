<?php

namespace Tests\Unit;

use App\Enums\FacilityGrantScaling;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * "An additional ... at 2, 3, 5, 8 etc Facilities" continues as 2 plus the
 * triangular numbers - 12, 17, 23 - rather than as the Fibonacci run.
 */
class FacilityGrantScalingTest extends TestCase
{
    public function test_the_thresholds_are_two_plus_the_triangular_numbers(): void
    {
        $this->assertSame(
            [2, 3, 5, 8, 12, 17, 23, 30],
            FacilityGrantScaling::thresholdsUpTo(30),
        );
    }

    /**
     * @return array<string, array{int, int}>
     */
    public static function steppingTotals(): array
    {
        return [
            'none' => [0, 0],
            'one' => [1, 1],
            'two' => [2, 2],
            'three' => [3, 3],
            'four is worth nothing more' => [4, 3],
            'five' => [5, 4],
            'eight' => [8, 5],
            'eleven is worth nothing more' => [11, 5],
            'twelve' => [12, 6],
            'thirteen is not a threshold' => [13, 6],
            'seventeen' => [17, 7],
        ];
    }

    #[DataProvider('steppingTotals')]
    public function test_a_stepping_effect_grows_at_each_threshold(int $count, int $expected): void
    {
        $this->assertSame($expected, FacilityGrantScaling::Thresholds->total(1, $count));
    }

    public function test_a_flat_effect_grows_with_every_facility(): void
    {
        $this->assertSame(6, FacilityGrantScaling::PerFacility->total(2, 3));
    }
}
