<?php

declare(strict_types=1);

namespace Fando\Keeper\Engine;

/**
 * A player a team is proposing to hold for the upcoming season, carrying the
 * state accumulated as of the end of last season.
 */
final class HoldCandidate
{
    public function __construct(
        public readonly int $playerId,
        public readonly string $name,
        public readonly int $draftRound,
        public readonly int $currentEffectiveHoldYear,
        public readonly int $currentAcceleratedCount,
    ) {
    }
}
