<?php

declare(strict_types=1);

namespace Fando\Keeper\Tests;

use Fando\Keeper\Engine\PickAssignment;
use PHPUnit\Framework\TestCase;

final class PickAssignmentTest extends TestCase
{
    public function testSimpleNonCollidingAssignment(): void
    {
        $result = PickAssignment::assign(
            requiredCostsByPlayerId: [1 => 3, 2 => 8],
            ownedPickRounds: [1, 2, 3, 4, 5, 6, 7, 8, 9, 10],
        );

        $this->assertTrue($result->legal);
        $this->assertSame(3, $result->assignments[1]);
        $this->assertSame(8, $result->assignments[2]);
    }

    /**
     * Regression test for the worked example from league planning:
     * Team holds three players with natural/accelerated costs of round 1,
     * round 5, and round 5 (the latter having just accelerated up from 6).
     * The team owns every pick except its 4th rounder (traded away). Result
     * should be 1st round pick, 5th round pick, and 3rd round pick (bumped up
     * because both the 5th and 4th were unavailable to the second round-5 hold).
     */
    public function testCollisionBuysUpToNextAvailablePickWhenTradedAwayPickIsMissing(): void
    {
        $ownedPicks = [1, 2, 3, 5, 6, 7, 8, 9, 10]; // no 4th rounder

        $result = PickAssignment::assign(
            requiredCostsByPlayerId: [
                'onePlayer' => 1,
                'fivePlayer' => 5,
                'acceleratedPlayer' => 5,
            ],
            ownedPickRounds: $ownedPicks,
        );

        $this->assertTrue($result->legal);
        $this->assertSame(1, $result->assignments['onePlayer']);
        $this->assertSame(5, $result->assignments['fivePlayer']);
        $this->assertSame(3, $result->assignments['acceleratedPlayer']);
    }

    public function testIllegalWhenNoSufficientPickRemains(): void
    {
        $result = PickAssignment::assign(
            requiredCostsByPlayerId: [1 => 2, 2 => 2],
            ownedPickRounds: [2, 5, 6], // only one pick good enough for a round-2 requirement
        );

        $this->assertFalse($result->legal);
        $this->assertSame(2, $result->unresolvable[2]);
    }

    public function testCanUseDuplicateTradedForPickInSameRound(): void
    {
        $result = PickAssignment::assign(
            requiredCostsByPlayerId: [1 => 4, 2 => 4],
            ownedPickRounds: [4, 4, 6, 7], // traded for an extra 4th rounder
        );

        $this->assertTrue($result->legal);
        $this->assertSame(4, $result->assignments[1]);
        $this->assertSame(4, $result->assignments[2]);
    }
}
