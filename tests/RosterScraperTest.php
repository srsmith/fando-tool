<?php

declare(strict_types=1);

namespace Fando\Keeper\Tests;

use Fando\Keeper\Scraper\RosterScraper;
use PHPUnit\Framework\TestCase;

/**
 * These fixtures are our best guess at CBS's markup shape (a heading
 * followed by a table with Player/Pos/Round/Next Yr/Accelerated columns),
 * not a captured real page. They pin down the parser's *behavior*
 * (header-alias matching, id extraction from links) so regressions are
 * caught once we swap in real captured HTML.
 */
final class RosterScraperTest extends TestCase
{
    public function testParsesPlayersFromHeaderAliasedTable(): void
    {
        $html = <<<HTML
        <html><body>
        <h2>Team Chaos</h2>
        <table>
            <tr><th>Player</th><th>Pos</th><th>Round</th><th>Next Yr</th><th>Accelerated</th></tr>
            <tr>
                <td><a href="/players/12345/sam-laporta">Sam LaPorta</a></td>
                <td>TE</td>
                <td>17</td>
                <td>11</td>
                <td>2</td>
            </tr>
        </table>
        </body></html>
        HTML;

        $players = (new RosterScraper())->parse($html, 'https://4and1.football.cbssports.com/teams/all');

        $this->assertCount(1, $players);
        $player = $players[0];
        $this->assertSame('12345', $player->cbsPlayerId);
        $this->assertSame('Sam LaPorta', $player->name);
        $this->assertSame('TE', $player->position);
        $this->assertSame('Team Chaos', $player->teamName);
        $this->assertSame(17, $player->draftRound);
        $this->assertSame(11, $player->nextYearRound);
        $this->assertSame(2, $player->accelerated);
    }

    public function testIgnoresTablesWithoutAPlayerColumn(): void
    {
        $html = '<table><tr><th>Foo</th><th>Bar</th></tr><tr><td>1</td><td>2</td></tr></table>';
        $players = (new RosterScraper())->parse($html, 'https://example.test');
        $this->assertSame([], $players);
    }
}
