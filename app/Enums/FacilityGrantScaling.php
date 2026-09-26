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
     * The first count at which a stepping effect grows.
     *
     * The briefings give 2, 3, 5 and 8 and then say "etc". The gaps between
     * them are 1, 2, 3, so the run carries on growing by one more each step -
     * 12, 17, 23 - which is 2 plus the triangular numbers. It is the designer's
     * ruling rather than a reading: those four are also the start of the
     * Fibonacci run, and 13 is where the two part company.
     */
    public const FIRST_THRESHOLD = 2;

    /**
     * The counts at which a stepping effect grows, up to and including $count.
     *
     * @return array<int, int>
     */
    public static function thresholdsUpTo(int $count): array
    {
        $thresholds = [];

        for ($step = 0, $threshold = self::FIRST_THRESHOLD; $threshold <= $count; $threshold += ++$step) {
            $thresholds[] = $threshold;
        }

        return $thresholds;
    }

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
            self::Thresholds => $base + count(self::thresholdsUpTo($count)),
        };
    }
}
