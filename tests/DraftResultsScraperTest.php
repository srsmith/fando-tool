<?php

declare(strict_types=1);

namespace Fando\Keeper\Tests;

use Fando\Keeper\Scraper\DraftResultsScraper;
use PHPUnit\Framework\TestCase;

/**
 * Fixture-based, same caveat as RosterScraperTest: guesses at CBS's markup
 * shape, pinning parser behavior (trade annotation extraction) ahead of
 * validating against a real captured page.
 */
final class DraftResultsScraperTest extends TestCase
{
    public function testParsesOwnedAndTradedPicks(): void
    {
        $html = <<<HTML
        <table>
            <tr><th>Round</th><th>Team</th></tr>
            <tr><td>1</td><td>Team Chaos</td></tr>
            <tr><td>4</td><td>Team Rival (from Team Chaos)</td></tr>
        </table>
        HTML;

        $picks = (new DraftResultsScraper())->parse($html);

        $this->assertCount(2, $picks);

        $this->assertSame(1, $picks[0]->round);
        $this->assertSame('Team Chaos', $picks[0]->owningTeamName);
        $this->assertNull($picks[0]->originalTeamName);

        $this->assertSame(4, $picks[1]->round);
        $this->assertSame('Team Rival', $picks[1]->owningTeamName);
        $this->assertSame('Team Chaos', $picks[1]->originalTeamName);
    }
}
