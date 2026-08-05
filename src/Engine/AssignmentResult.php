<?php

declare(strict_types=1);

namespace Fando\Keeper\Engine;

final class AssignmentResult
{
    /**
     * @param array<int,int> $assignments playerId => pick round actually spent
     * @param array<int,int> $unresolvable playerId => required round that couldn't be covered
     * @param int[] $remainingPicks picks left over after assignment
     */
    public function __construct(
        public readonly bool $legal,
        public readonly array $assignments,
        public readonly array $unresolvable,
        public readonly array $remainingPicks,
    ) {
    }
}
