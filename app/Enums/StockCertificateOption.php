<?php

namespace App\Enums;

/**
 * The two ways a Stock Certificate can be cashed in.
 *
 * The card reads: "Take a half (rounded down) of the corporation income as
 * credits and reduce their income by 1, or take a quarter (rounded up) of the
 * corporation's income as credits." Both halves read the issuing
 * Corporation's Income at the moment of cashing, because Income is what a
 * certificate is a share of - so the same certificate is worth more once the
 * Corporation has grown, and a holder deciding when to cash it is making the
 * real decision the card offers.
 */
enum StockCertificateOption: string
{
    /** Half the Income, rounded down, and the Corporation's Income drops by 1. */
    case Half = 'half';

    /** A quarter of the Income, rounded up, and nothing else moves. */
    case Quarter = 'quarter';

    public function label(): string
    {
        return match ($this) {
            self::Half => 'Half, and cut their Income',
            self::Quarter => 'A quarter',
        };
    }

    /**
     * The Credits this pays out against the Corporation's current Income.
     *
     * An Income of nought or less pays nothing either way: a share of nothing
     * is nothing, and a negative share would have the holder paying for the
     * privilege.
     */
    public function creditsFor(int $income): int
    {
        $income = max(0, $income);

        return match ($this) {
            self::Half => intdiv($income, 2),
            self::Quarter => intdiv($income + 3, 4),
        };
    }

    /**
     * How far the issuing Corporation's Income falls.
     */
    public function incomeReduction(): int
    {
        return match ($this) {
            self::Half => 1,
            self::Quarter => 0,
        };
    }
}
