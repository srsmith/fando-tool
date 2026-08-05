<?php

declare(strict_types=1);

namespace Fando\Keeper\Engine;

/**
 * Checks whether a team actually owns enough draft picks to cover its
 * proposed holds, and if so, which specific pick pays for which player.
 *
 * A hold requiring round R can be paid with any owned pick of round R or
 * better (a lower round number -- e.g. a 1st rounder can cover a round-5
 * requirement). It can never be paid with a worse (higher-numbered) pick.
 *
 * Per league owner: when two holds collide on the same round, it doesn't
 * matter *which* specific player ends up "buying up" to a better pick --
 * only whether a legal assignment exists at all, and what picks it spends.
 * So this always resolves the tightest requirement first (round 1 can only
 * ever be paid by an actual 1st-round pick) and, for each requirement, uses
 * the least wasteful sufficient pick still available -- which both
 * guarantees an optimal/feasible assignment when one exists, and matches
 * the worked example from planning.
 */
final class PickAssignment
{
    /**
     * @param array<int,int> $requiredCostsByPlayerId playerId => required round
     * @param int[] $ownedPickRounds the team's currently owned pick rounds (may
     *              contain duplicate rounds if picks were traded for)
     */
    public static function assign(array $requiredCostsByPlayerId, array $ownedPickRounds): AssignmentResult
    {
        asort($requiredCostsByPlayerId);

        $available = array_values($ownedPickRounds);
        sort($available);

        $assignments = [];
        $unresolvable = [];

        foreach ($requiredCostsByPlayerId as $playerId => $required) {
            $bestIndex = null;
            foreach ($available as $idx => $round) {
                if ($round <= $required && ($bestIndex === null || $round > $available[$bestIndex])) {
                    $bestIndex = $idx;
                }
            }

            if ($bestIndex === null) {
                $unresolvable[$playerId] = $required;
                continue;
            }

            $assignments[$playerId] = $available[$bestIndex];
            unset($available[$bestIndex]);
        }

        return new AssignmentResult(
            legal: count($unresolvable) === 0,
            assignments: $assignments,
            unresolvable: $unresolvable,
            remainingPicks: array_values($available),
        );
    }
}
