<?php

declare(strict_types=1);

namespace Fando\Keeper\Scraper;

use Fando\Keeper\Scraper\Dto\RosterPlayer;

/**
 * Parses https://4and1.football.cbssports.com/teams/all
 *
 * NOT YET VALIDATED against a live page -- cbssports.com is unreachable from
 * this sandbox (org egress policy). This parser works off the page structure
 * described during planning (one roster table per team, with Round /
 * Next Year / Accelerated columns) using header-text matching rather than
 * hardcoded CSS classes, so it should tolerate CBS's exact markup reasonably
 * well, but it needs to be run against a real captured page (see
 * scripts/capture_pages.php) and adjusted if column names/structure differ.
 *
 * @see README-scraper.md for the validation checklist.
 */
final class RosterScraper
{
    private const FIELD_ALIASES = [
        'player' => ['player', 'name'],
        'position' => ['pos'],
        'round' => ['round', 'rd'],
        'nextYear' => ['next yr', 'next year'],
        'accelerated' => ['accel'],
    ];

    /** @return RosterPlayer[] */
    public function parse(string $html, string $baseUrl): array
    {
        $doc = new \DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadHTML($html);
        libxml_use_internal_errors(false);
        $xpath = new \DOMXPath($doc);

        $players = [];

        foreach ($xpath->query('//table') as $table) {
            /** @var \DOMElement $table */
            $teamName = $this->findPrecedingTeamName($xpath, $table);
            $headerMap = $this->mapHeaders($xpath, $table);

            if (!isset($headerMap['player'])) {
                continue; // not a roster table
            }

            foreach ($xpath->query('.//tr', $table) as $row) {
                /** @var \DOMElement $row */
                $cells = $xpath->query('./td', $row);
                if ($cells === false || $cells->length === 0) {
                    continue; // header row
                }

                $playerCell = $cells->item($headerMap['player']);
                if ($playerCell === null || trim($playerCell->textContent) === '') {
                    continue;
                }

                $playerLink = $xpath->query('.//a', $playerCell)->item(0);
                $cbsPlayerId = $playerLink instanceof \DOMElement
                    ? $this->extractIdFromHref($playerLink->getAttribute('href'))
                    : null;

                $players[] = new RosterPlayer(
                    cbsPlayerId: $cbsPlayerId ?? trim($playerCell->textContent),
                    name: trim($playerCell->textContent),
                    position: $this->cellText($cells, $headerMap['position'] ?? null),
                    teamName: $teamName,
                    draftRound: $this->cellInt($cells, $headerMap['round'] ?? null),
                    nextYearRound: $this->cellInt($cells, $headerMap['nextYear'] ?? null),
                    accelerated: $this->cellInt($cells, $headerMap['accelerated'] ?? null) ?? 0,
                );
            }
        }

        return $players;
    }

    /** @return array<string,int> field name => column index */
    private function mapHeaders(\DOMXPath $xpath, \DOMElement $table): array
    {
        $headerRow = $xpath->query('.//tr[th]', $table)->item(0);
        if ($headerRow === null) {
            $headerRow = $xpath->query('.//tr', $table)->item(0);
        }
        if ($headerRow === null) {
            return [];
        }

        $map = [];
        $cells = $xpath->query('./th|./td', $headerRow);
        foreach ($cells as $i => $cell) {
            $text = strtolower(trim($cell->textContent));
            foreach (self::FIELD_ALIASES as $field => $aliases) {
                foreach ($aliases as $alias) {
                    if (str_contains($text, $alias)) {
                        $map[$field] = $i;
                        continue 2;
                    }
                }
            }
        }
        return $map;
    }

    private function findPrecedingTeamName(\DOMXPath $xpath, \DOMElement $table): ?string
    {
        $heading = $xpath->query('preceding::h1[1]|preceding::h2[1]|preceding::h3[1]', $table);
        if ($heading !== false && $heading->length > 0) {
            return trim($heading->item($heading->length - 1)->textContent);
        }
        return null;
    }

    private function extractIdFromHref(string $href): ?string
    {
        if (preg_match('/(\d+)(?:[^\d]*)?$/', $href, $m)) {
            return $m[1];
        }
        return null;
    }

    private function cellText(\DOMNodeList $cells, ?int $index): ?string
    {
        if ($index === null || $cells->item($index) === null) {
            return null;
        }
        $text = trim($cells->item($index)->textContent);
        return $text === '' ? null : $text;
    }

    private function cellInt(\DOMNodeList $cells, ?int $index): ?int
    {
        $text = $this->cellText($cells, $index);
        if ($text === null || !preg_match('/\d+/', $text, $m)) {
            return null;
        }
        return (int) $m[0];
    }
}
