<?php

namespace App\Enums;

/**
 * How a Facility type's effect grows with the number of them a Corporation owns.
 *
 * The briefing documents use two shapes. Security and Corporate Facilities are
 * flat: each one adds its effect again ("1 more Physical ... in each of your
 * Facilities"). Research, AI School, Factory, Mini-factory and Arms instead
 * step, giving their effect once and then "an additional ... at 2, 3, 5, 8 etc
 * Facilities", so the fourth Facility of the type is worth nothing and the
 * fifth is worth one more.
 */
enum FacilityGrantScaling: string
{
    case PerFacility = 'per_facility';
    case Thresholds = 'thresholds';

    /**
     * The counts at which a stepping effect grows.
     *
     * The briefings give 2, 3, 5 and 8 and then say "etc", which continues the
     * Fibonacci run those four are the start of. 13 and 21 are that reading
     * rather than anything written down, and a game reaching thirteen Factories
     * has bigger questions than this list.
     *
     * @var array<int, int>
     */
    public const THRESHOLDS = [2, 3, 5, 8, 13, 21];

    public function label(): string
    {
        return match ($this) {
            self::PerFacility => 'Per Facility',
            self::Thresholds => 'Steps at 2, 3, 5, 8',
        };
    }

    /**
     * What a base effect of $base is worth to a Corporation owning $count
     * Facilities of the type.
     */
    public function total(int $base, int $count): int
    {
        if ($base === 0 || $count === 0) {
            return 0;
        }

        return match ($this) {
            self::PerFacility => $base * $count,
            self::Thresholds => $base + count(array_filter(
                self::THRESHOLDS,
                fn (int $threshold): bool => $count >= $threshold,
            )),
        };
    }
}
