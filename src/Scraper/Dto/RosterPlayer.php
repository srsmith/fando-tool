<?php

declare(strict_types=1);

namespace Fando\Keeper\Scraper\Dto;

final class RosterPlayer
{
    public function __construct(
        public readonly string $cbsPlayerId,
        public readonly string $name,
        public readonly ?string $position,
        public readonly ?string $teamName,
        public readonly ?int $draftRound,
        public readonly ?int $nextYearRound,
        public readonly int $accelerated,
    ) {
    }
}
