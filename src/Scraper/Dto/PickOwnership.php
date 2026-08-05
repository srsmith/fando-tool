<?php

declare(strict_types=1);

namespace Fando\Keeper\Scraper\Dto;

final class PickOwnership
{
    public function __construct(
        public readonly int $round,
        public readonly string $owningTeamName,
        public readonly ?string $originalTeamName, // null if not traded
    ) {
    }
}
