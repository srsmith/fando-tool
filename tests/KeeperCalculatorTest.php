<?php

declare(strict_types=1);

namespace Fando\Keeper\Tests;

use Fando\Keeper\Engine\HoldCandidate;
use Fando\Keeper\Engine\KeeperCalculator;
use PHPUnit\Framework\TestCase;

final class KeeperCalculatorTest extends TestCase
{
    public function testEscalatorDeltaSequenceDoubles(): void
    {
        $this->assertSame(0, KeeperCalculator::delta(1));
        $this->assertSame(1, KeeperCalculator::delta(2));
        $this->assertSame(2, KeeperCalculator::delta(3));
        $this->assertSame(4, KeeperCalculator::delta(4));
        $this->assertSame(8, KeeperCalculator::delta(5));
        $this->assertSame(16, KeeperCalculator::delta(6));
        $this->assertSame(32, KeeperCalculator::delta(7));
    }

    public function testCostRoundFloorsAtOne(): void
    {
        $this->assertSame(1, KeeperCalculator::costRound(3, 6)); // 3 - 16 would go negative
    }

    /**
     * Regression test for the worked Sam LaPorta example from league planning:
     * drafted 2023 round 17, held every year, and the cheapest of his team's
     * three holds each time (so accelerated every year).
     *
     *   2024: considered a 2-year hold -> round 17-1 = 16
     *   2025: considered a 4-year hold -> round 17-4 = 13
     *   2026: considered a 6-year hold -> round 17-16 = 1
     */
    public function testLaPorteAcceleratedEveryYear(): void
    {
        $state = new HoldCandidate(
            playerId: 1,
            name: 'Sam LaPorta',
            draftRound: 17,
            currentEffectiveHoldYear: 0,
            currentAcceleratedCount: 0,
        );

        // 2024 decision: he's the cheapest of a 3-keeper team.
        [$resolved2024] = KeeperCalculator::resolveSeason([
            $state,
            $this->filler(1), // cheap filler holds so LaPorta is the cheapest of the three
            $this->filler(2),
        ]);
        $this->assertSame(2, $resolved2024->effectiveHoldYear);
        $this->assertSame(16, $resolved2024->costRound);
        $this->assertTrue($resolved2024->acceleratedThisSeason);
        $this->assertSame(1, $resolved2024->newAcceleratedCount());

        // 2025 decision: carry state forward, again the cheapest of three.
        $state = new HoldCandidate(1, 'Sam LaPorta', 17, $resolved2024->effectiveHoldYear, $resolved2024->newAcceleratedCount());
        [$resolved2025] = KeeperCalculator::resolveSeason([
            $state,
            $this->filler(1),
            $this->filler(2),
        ]);
        $this->assertSame(4, $resolved2025->effectiveHoldYear);
        $this->assertSame(13, $resolved2025->costRound);
        $this->assertSame(2, $resolved2025->newAcceleratedCount());

        // 2026 decision: still the cheapest of three -> "considered 6 year hold".
        $state = new HoldCandidate(1, 'Sam LaPorta', 17, $resolved2025->effectiveHoldYear, $resolved2025->newAcceleratedCount());
        [$resolved2026] = KeeperCalculator::resolveSeason([
            $state,
            $this->filler(1),
            $this->filler(2),
        ]);
        $this->assertSame(6, $resolved2026->effectiveHoldYear);
        $this->assertSame(1, $resolved2026->costRound);
    }

    public function testNoAccelerationWhenFewerThanThreeHeld(): void
    {
        $state = new HoldCandidate(1, 'Sam LaPorta', 17, 0, 0);

        [$resolved] = KeeperCalculator::resolveSeason([$state]);
        $this->assertSame(1, $resolved->effectiveHoldYear);
        $this->assertSame(17, $resolved->costRound);
        $this->assertFalse($resolved->acceleratedThisSeason);

        [$resolvedA, $resolvedB] = KeeperCalculator::resolveSeason([$state, $this->filler(1)]);
        $this->assertFalse($resolvedA->acceleratedThisSeason);
        $this->assertFalse($resolvedB->acceleratedThisSeason);
    }

    public function testOnlyTheCheapestOfThreeAccelerates(): void
    {
        // Round-7 player, second time held (natural cost would be 6), sitting
        // alongside a round-1 and round-9 (much cheaper) held player -- the
        // round-9 player should be the one who accelerates, not this one.
        $subject = new HoldCandidate(2, 'Subject', 7, 1, 0);
        $cheapest = new HoldCandidate(3, 'Cheapest', 10, 0, 0); // natural cost round 10
        $expensive = new HoldCandidate(4, 'Expensive', 1, 0, 0); // natural cost round 1

        [$resolvedSubject, $resolvedCheapest, $resolvedExpensive] = KeeperCalculator::resolveSeason([
            $subject,
            $cheapest,
            $expensive,
        ]);

        $this->assertFalse($resolvedSubject->acceleratedThisSeason);
        $this->assertSame(6, $resolvedSubject->costRound);

        $this->assertTrue($resolvedCheapest->acceleratedThisSeason);
        $this->assertFalse($resolvedExpensive->acceleratedThisSeason);
    }

    /** A cheap round-10 filler held player, never previously held, used to pad out 3-keeper scenarios. */
    private function filler(int $id): HoldCandidate
    {
        return new HoldCandidate($id + 100, "Filler{$id}", draftRound: 1, currentEffectiveHoldYear: 0, currentAcceleratedCount: 0);
    }
}
