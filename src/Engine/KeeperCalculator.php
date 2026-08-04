<?php

declare(strict_types=1);

namespace Fando\Keeper\Engine;

/**
 * FANDO keeper cost escalator.
 *
 * Round cost = draft_round - delta(effective_hold_year), floored at round 1.
 * delta(1) = 0, delta(n) = 2^(n-2) for n >= 2 (0, 1, 2, 4, 8, 16, 32, ...).
 *
 * "effective hold year" already folds in any permanent acceleration from prior
 * seasons -- it is not the same as how many chronological seasons the player
 * has actually been rostered.
 */
final class KeeperCalculator
{
    public const MAX_HELD = 3;

    public static function delta(int $effectiveHoldYear): int
    {
        if ($effectiveHoldYear <= 1) {
            return 0;
        }

        return 2 ** ($effectiveHoldYear - 2);
    }

    public static function costRound(int $draftRound, int $effectiveHoldYear): int
    {
        return max(1, $draftRound - self::delta($effectiveHoldYear));
    }

    /**
     * Resolve a team's proposed holds for the upcoming season.
     *
     * Every candidate advances one effective hold year. If exactly three
     * players are held, the single candidate whose *natural* (pre-acceleration)
     * cost round is highest -- i.e. the cheapest hold -- advances one further
     * year and permanently gains one acceleration. Ties are broken by input
     * order; the league rule doesn't specify a tiebreak, and per league owner
     * confirmation it doesn't matter which specific player breaks a tie
     * because it has no effect on future value, only on which picks get
     * spent this draft.
     *
     * @param HoldCandidate[] $candidates 0-3 players a team wants to hold
     * @return ResolvedHold[] same order as input
     */
    public static function resolveSeason(array $candidates): array
    {
        if (count($candidates) > self::MAX_HELD) {
            throw new \InvalidArgumentException('A team may hold at most ' . self::MAX_HELD . ' players.');
        }

        $naturalYear = [];
        $naturalCost = [];
        foreach ($candidates as $i => $candidate) {
            $naturalYear[$i] = $candidate->currentEffectiveHoldYear + 1;
            $naturalCost[$i] = self::costRound($candidate->draftRound, $naturalYear[$i]);
        }

        $acceleratedIndex = null;
        if (count($candidates) === self::MAX_HELD) {
            $acceleratedIndex = array_keys($naturalCost, max($naturalCost))[0];
        }

        $resolved = [];
        foreach ($candidates as $i => $candidate) {
            $accelerated = ($i === $acceleratedIndex);
            $effectiveYear = $naturalYear[$i] + ($accelerated ? 1 : 0);

            $resolved[] = new ResolvedHold(
                player: $candidate,
                effectiveHoldYear: $effectiveYear,
                costRound: self::costRound($candidate->draftRound, $effectiveYear),
                acceleratedThisSeason: $accelerated,
            );
        }

        return $resolved;
    }
}
