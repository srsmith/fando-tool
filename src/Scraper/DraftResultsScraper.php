<?php

declare(strict_types=1);

namespace Fando\Keeper\Scraper;

use Fando\Keeper\Scraper\Dto\PickOwnership;

/**
 * Parses https://4and1.football.cbssports.com/draft/results into which team
 * currently owns each round's pick (post-trade). Same caveat as
 * RosterScraper: not yet validated against a live page. Assumes CBS marks a
 * traded pick with an annotation like "(from Team X)" next to the owning
 * team's name, which is the common CBS Sportsline convention on draft
 * boards -- needs confirming against a real capture.
 *
 * @see README-scraper.md for the validation checklist.
 */
final class DraftResultsScraper
{
    private const TRADE_ANNOTATION = '/\((?:from|via)\s+([^)]+)\)/i';

    /** @return PickOwnership[] */
    public function parse(string $html): array
    {
        $doc = new \DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadHTML($html);
        libxml_use_internal_errors(false);
        $xpath = new \DOMXPath($doc);

        $picks = [];

        foreach ($xpath->query('//table') as $table) {
            /** @var \DOMElement $table */
            $headerMap = $this->mapHeaders($xpath, $table);
            if (!isset($headerMap['round']) || !isset($headerMap['team'])) {
                continue;
            }

            foreach ($xpath->query('.//tr', $table) as $row) {
                /** @var \DOMElement $row */
                $cells = $xpath->query('./td', $row);
                if ($cells === false || $cells->length === 0) {
                    continue;
                }

                $roundText = trim($cells->item($headerMap['round'])->textContent ?? '');
                if (!preg_match('/\d+/', $roundText, $m)) {
                    continue;
                }
                $round = (int) $m[0];

                $teamCellText = trim($cells->item($headerMap['team'])->textContent ?? '');
                if ($teamCellText === '') {
                    continue;
                }

                $originalTeam = null;
                if (preg_match(self::TRADE_ANNOTATION, $teamCellText, $tm)) {
                    $originalTeam = trim($tm[1]);
                    $owningTeam = trim((string) preg_replace(self::TRADE_ANNOTATION, '', $teamCellText));
                } else {
                    $owningTeam = $teamCellText;
                }

                $picks[] = new PickOwnership(
                    round: $round,
                    owningTeamName: $owningTeam,
                    originalTeamName: $originalTeam,
                );
            }
        }

        return $picks;
    }

    /** @return array<string,int> */
    private function mapHeaders(\DOMXPath $xpath, \DOMElement $table): array
    {
        $headerRow = $xpath->query('.//tr[th]', $table)->item(0) ?? $xpath->query('.//tr', $table)->item(0);
        if ($headerRow === null) {
            return [];
        }

        $aliases = [
            'round' => ['round', 'rd'],
            'team' => ['team', 'owner'],
        ];

        $map = [];
        foreach ($xpath->query('./th|./td', $headerRow) as $i => $cell) {
            $text = strtolower(trim($cell->textContent));
            foreach ($aliases as $field => $needles) {
                foreach ($needles as $needle) {
                    if (str_contains($text, $needle)) {
                        $map[$field] = $i;
                        continue 2;
                    }
                }
            }
        }
        return $map;
    }
}
