<?php

declare(strict_types=1);

namespace Fando\Keeper\Engine;

/**
 * The result of running KeeperCalculator::resolveSeason() for one held player.
 */
final class ResolvedHold
{
    public function __construct(
        public readonly HoldCandidate $player,
        public readonly int $effectiveHoldYear,
        public readonly int $costRound,
        public readonly bool $acceleratedThisSeason,
    ) {
    }

    /** accelerated_count to persist if this resolution is committed. */
    public function newAcceleratedCount(): int
    {
        return $this->player->currentAcceleratedCount + ($this->acceleratedThisSeason ? 1 : 0);
    }
}
