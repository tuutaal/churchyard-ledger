<?php
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Anesti;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Throwable;

/**
 * Imports a public cemetery directory published as a set of WordPress table
 * pages (the church/site is not hardcoded - see scripts/directory-import.config.example.json)
 * into people/plots/interments/media, matching the existing schema and
 * grave-photo upload convention. Built against a "Surname | Given Name |
 * Birth | Death | Inscriptions/Editor's Notes | Location" table layout, a
 * common shape for church cemetery directories published on WordPress; any
 * church with a similarly-shaped table can reuse it by pointing the config
 * file at their own pages.
 *
 * The source table lists one row per photo/marker, not one row per person:
 * the same person is often listed twice (an "Original Marker" row and a
 * "New Marker" row, or a full-detail row plus a "Father"/"Mother" row with
 * a second photo). Rows that share a normalized name and plot location are
 * grouped, then split back into distinct people only when their birth/death
 * text actually conflicts (e.g. two different infants with the same surname
 * buried in the same family plot). See clusterByDates().
 *
 * Idempotency: each resolved person is identified by a key derived from
 * their normalized name, location, and resolved birth/death text, stored in
 * a caller-supplied state array. Re-running with corrected notes or an
 * additional photo updates the same record instead of duplicating it.
 * Admin-curated fields (visibility, confidence, notes, status, disposition)
 * are preserved on update via Repository::upsertPerson()/upsertPlot()/upsertInterment().
 */
final class PublicDirectoryImport
{
    private bool $blockCustomFieldEnsured = false;

    public function __construct(
        private readonly Repository $repository,
        private readonly string $root,
        private readonly string $photoBaseUrl
    ) {
    }

    public function fetchPage(string $url): ?string
    {
        return $this->fetchUrl($url);
    }

    /**
     * Parses the directory table into raw rows. Public so it can be unit-tested
     * against saved HTML fixtures without a live network fetch.
     */
    public function parseTable(string $html): array
    {
        $doc = new DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8">' . $html);
        libxml_clear_errors();

        $xpath = new DOMXPath($doc);
        $rows = [];
        foreach ($xpath->query('//table[contains(concat(" ", normalize-space(@class), " "), " wp-block-table ")]//tr') as $tr) {
            $cells = $xpath->query('./td', $tr);
            if ($cells === false || $cells->length < 6) {
                continue;
            }

            $surnameCell = $cells->item(0);
            $link = $xpath->query('.//a', $surnameCell)->item(0);

            $rows[] = [
                'surname' => $this->cleanText($surnameCell->textContent ?? ''),
                'given_name' => $this->cleanText($cells->item(1)->textContent ?? ''),
                'birth' => $this->cleanText($cells->item(2)->textContent ?? ''),
                'death' => $this->cleanText($cells->item(3)->textContent ?? ''),
                'notes' => $this->cleanText($cells->item(4)->textContent ?? ''),
                'location_raw' => $this->cleanText($cells->item(5)->textContent ?? ''),
                'photo_href' => $link instanceof DOMElement ? trim($link->getAttribute('href')) : '',
            ];
        }

        return $rows;
    }

    /**
     * Normalizes the two known Location formats (Sec/Row/Blk, in either token
     * order, and Sec/Lot with an optional letter suffix and compass direction),
     * plus "No Marker"/blank. Anything else is flagged as ambiguous rather than
     * guessed - e.g. a typo'd "Rpw" for "Row" is left for a human to fix.
     */
    public function normalizeLocation(string $raw): array
    {
        $text = $this->cleanText($raw);

        if ($text === '' || preg_match('/^no\s*marker\.?$/i', $text) === 1) {
            return ['type' => 'none', 'raw' => $raw];
        }

        if (preg_match('/^Sec(?:tion)?\.?\s*(\d+)\.?\s*Row\s*(\d+)\s*Blk\.?\s*(\d+)$/i', $text, $m) === 1) {
            return $this->blockLocation((int) $m[1], (int) $m[2], (int) $m[3], $raw);
        }

        if (preg_match('/^Sec(?:tion)?\.?\s*(\d+)\.?\s*Blk\.?\s*(\d+)\s*Row\s*(\d+)$/i', $text, $m) === 1) {
            return $this->blockLocation((int) $m[1], (int) $m[3], (int) $m[2], $raw);
        }

        if (preg_match('/^Sec(?:tion)?\.?\s*(\d+)\.?\s*Lot\s*(\d+)\s*-?\s*([A-Za-z]?)\s*(East|West|North|South|E|W|N|S)?$/i', $text, $m) === 1) {
            $section = (int) $m[1];
            $suffix = strtoupper($m[3] ?? '');
            $direction = match (strtoupper(trim((string) ($m[4] ?? '')))) {
                'E', 'EAST' => 'East',
                'W', 'WEST' => 'West',
                'N', 'NORTH' => 'North',
                'S', 'SOUTH' => 'South',
                default => '',
            };
            $lot = $m[2] . $suffix . ($direction !== '' ? ' ' . $direction : '');

            return [
                'type' => 'lot',
                'section' => $section,
                'lot' => $lot,
                'section_code' => 'Sec ' . $section,
                'identifier' => sprintf('Sec %d Lot %s', $section, $lot),
                'raw' => $raw,
            ];
        }

        return ['type' => 'ambiguous', 'raw' => $raw];
    }

    private function blockLocation(int $section, int $row, int $block, string $raw): array
    {
        return [
            'type' => 'block',
            'section' => $section,
            'row' => $row,
            'block' => $block,
            'section_code' => 'Sec ' . $section,
            'identifier' => sprintf('Sec %d Blk %d Row %d', $section, $block, $row),
            'raw' => $raw,
        ];
    }

    /**
     * @param array<string,string> $pages label => already-fetched HTML
     * @param array $state mutable identity/state map, persisted by the caller between runs
     * @param array{visibility?:string,dry_run?:bool} $options
     */
    public function run(array $pages, array &$state, array $options = []): array
    {
        $visibility = in_array($options['visibility'] ?? 'public', ['public', 'private'], true)
            ? $options['visibility']
            : 'public';
        $dryRun = !empty($options['dry_run']);
        $state['people'] ??= [];

        $report = [
            'rows_processed' => 0,
            'people_created' => 0,
            'people_updated' => 0,
            'rows_merged_into_existing_person' => 0,
            'plots_created_or_reused' => 0,
            'interments_created' => 0,
            'interments_updated' => 0,
            'photos_downloaded' => 0,
            'photos_skipped_unchanged' => 0,
            'photos_failed' => 0,
            'rows_flagged' => 0,
            'flagged' => [],
        ];

        $namedRows = [];
        foreach ($pages as $page => $html) {
            foreach ($this->parseTable($html) as $index => $row) {
                $report['rows_processed']++;
                $row['_page'] = (string) $page;
                $row['_row'] = $index + 1;

                if ($row['surname'] === '' && $row['given_name'] === '') {
                    $this->flagRow($report, $row, 'Missing surname and given name.');
                    continue;
                }

                if ($this->isPlaceholderName($row['surname']) && $this->isPlaceholderName($row['given_name'])) {
                    $this->processCluster($this->singletonCluster($row, true), $state, $visibility, $dryRun, $report);
                    continue;
                }

                $namedRows[] = $row;
            }
        }

        foreach ($this->groupByNameAndLocation($namedRows) as $group) {
            foreach ($this->clusterByDates($group, $report) as $cluster) {
                $this->processCluster($cluster, $state, $visibility, $dryRun, $report);
            }
        }

        $report['rows_flagged'] = count($report['flagged']);

        return $report;
    }

    /**
     * Groups rows sharing a normalized (surname, given name, location) so
     * clusterByDates() can decide whether they describe one person or several.
     */
    private function groupByNameAndLocation(array $rows): array
    {
        $groups = [];
        foreach ($rows as $row) {
            $location = $this->normalizeLocation($row['location_raw']);
            $locationKey = $location['identifier'] ?? ($location['type'] . '|' . $location['raw']);
            $groupKey = $this->normalizeName($row['surname']) . '|' . $this->normalizeName($row['given_name']) . '|' . $locationKey;
            $row['_location'] = $location;
            $groups[$groupKey][] = $row;
        }

        return array_values($groups);
    }

    /**
     * Splits a name+location group into clusters representing distinct people.
     * Rows merge into the same cluster when their birth/death text does not
     * conflict (blank is compatible with anything); a concrete date mismatch
     * (e.g. two different infants) starts a new cluster. A group that never
     * gets any distinguishing date at all is flagged for manual review since
     * there is no way to tell whether it is one person or several.
     */
    private function clusterByDates(array $rows, array &$report): array
    {
        $clusters = [];
        foreach ($rows as $row) {
            $birth = $row['birth'];
            $death = $row['death'];
            $matchIndex = null;
            foreach ($clusters as $i => $cluster) {
                $birthOk = $birth === '' || $cluster['birth'] === '' || $birth === $cluster['birth'];
                $deathOk = $death === '' || $cluster['death'] === '' || $death === $cluster['death'];
                $hasSignal = $birth !== '' || $death !== '' || $cluster['birth'] !== '' || $cluster['death'] !== '';
                if ($birthOk && $deathOk && $hasSignal) {
                    $matchIndex = $i;
                    break;
                }
            }

            if ($matchIndex === null) {
                $clusters[] = ['birth' => $birth, 'death' => $death, 'rows' => [$row]];
                continue;
            }

            if ($clusters[$matchIndex]['birth'] === '') {
                $clusters[$matchIndex]['birth'] = $birth;
            }
            if ($clusters[$matchIndex]['death'] === '') {
                $clusters[$matchIndex]['death'] = $death;
            }
            $clusters[$matchIndex]['rows'][] = $row;
        }

        if (count($clusters) > 1) {
            foreach ($clusters as $cluster) {
                if ($cluster['birth'] === '' && $cluster['death'] === '') {
                    foreach ($cluster['rows'] as $row) {
                        $this->flagRow($report, $row, 'Same name and plot location as another entry, with no birth/death date to tell them apart - imported as a separate person; please verify manually.');
                    }
                }
            }
        }

        return array_map(fn (array $c) => ['birth' => $c['birth'], 'death' => $c['death'], 'rows' => $c['rows'], 'unknown_identity' => false], $clusters);
    }

    private function singletonCluster(array $row, bool $unknownIdentity): array
    {
        return ['birth' => $row['birth'], 'death' => $row['death'], 'rows' => [$row], 'unknown_identity' => $unknownIdentity];
    }

    private function processCluster(array $cluster, array &$state, string $visibility, bool $dryRun, array &$report): void
    {
        $rows = $cluster['rows'];
        $first = $rows[0];
        $surname = $first['surname'];
        $given = $first['given_name'];
        $isUnknownIdentity = $cluster['unknown_identity'];

        $legalName = $isUnknownIdentity ? 'Unknown' : trim($given . ' ' . $surname);
        if ($legalName === '') {
            $this->flagRow($report, $first, 'Empty name after normalization.');
            return;
        }

        $location = $isUnknownIdentity ? $this->normalizeLocation($first['location_raw']) : $first['_location'];
        if ($location['type'] === 'ambiguous') {
            $this->flagRow($report, $first, 'Could not parse Location "' . $first['location_raw'] . '" - imported without a plot link.');
        }

        if (count($rows) > 1) {
            $report['rows_merged_into_existing_person'] += count($rows) - 1;
        }

        $notes = [];
        $photoHrefs = [];
        foreach ($rows as $row) {
            if ($row['notes'] !== '' && !in_array($row['notes'], $notes, true)) {
                $notes[] = $row['notes'];
            }
            if ($row['photo_href'] !== '' && !in_array($row['photo_href'], $photoHrefs, true)) {
                $photoHrefs[] = $row['photo_href'];
            }
        }

        $key = $this->sourceKey($surname, $given, $location, $cluster['birth'], $cluster['death'], $isUnknownIdentity, $rows);
        $existing = $state['people'][$key] ?? null;

        if ($dryRun) {
            $existing === null ? $report['people_created']++ : $report['people_updated']++;
            if (in_array($location['type'], ['block', 'lot'], true)) {
                $report['plots_created_or_reused']++;
            }
            ($existing['interment_id'] ?? null) === null ? $report['interments_created']++ : $report['interments_updated']++;
            return;
        }

        $personData = [
            'legal_name' => $legalName,
            'given_name' => $isUnknownIdentity ? null : ($given !== '' ? $given : null),
            'family_name' => $isUnknownIdentity ? null : ($surname !== '' ? $surname : null),
            'birth_date_text' => $cluster['birth'] !== '' ? $cluster['birth'] : null,
            'death_date_text' => $cluster['death'] !== '' ? $cluster['death'] : null,
        ];
        if ($existing === null) {
            $personData['visibility'] = $visibility;
            $personData['confidence'] = 'probable';
            if ($isUnknownIdentity) {
                $personData['notes'] = 'Identity unknown - imported from the public directory "Unknown" listing.';
            }
        }

        $personId = $this->repository->upsertPerson($existing['person_id'] ?? null, $personData);
        if ($personId === null) {
            $this->flagRow($report, $first, 'Could not save this person record.');
            return;
        }
        $existing === null ? $report['people_created']++ : $report['people_updated']++;

        $plotId = null;
        if (in_array($location['type'], ['block', 'lot'], true)) {
            $sectionId = $this->repository->upsertSection($location['section_code'], 'Section ' . $location['section']);
            $plotData = [
                'identifier' => $location['identifier'],
                'section_id' => $sectionId,
                'row_label' => $location['type'] === 'block' ? (string) $location['row'] : null,
                'lot' => $location['type'] === 'lot' ? $location['lot'] : null,
            ];
            if ($location['type'] === 'block') {
                $this->ensureBlockCustomField();
                $plotData['custom'] = ['block' => (string) $location['block']];
            }
            $plotId = $this->repository->upsertPlot($plotData);
            if ($plotId !== null) {
                $report['plots_created_or_reused']++;
            }
        }

        $intermentData = [
            'person_id' => $personId,
            'plot_id' => $plotId,
            'marker_transcription' => $notes ? implode('; ', $notes) : null,
        ];
        $existingIntermentId = $existing['interment_id'] ?? null;
        if ($existingIntermentId === null) {
            $intermentData['visibility'] = $visibility;
            $intermentData['confidence'] = 'probable';
        }

        $intermentId = $this->repository->upsertInterment($existingIntermentId, $intermentData);
        if ($intermentId === null) {
            $this->flagRow($report, $first, 'Could not save this interment record.');
            $state['people'][$key] = ['person_id' => $personId, 'interment_id' => null, 'photo_sources' => $existing['photo_sources'] ?? []];
            return;
        }
        $existingIntermentId === null ? $report['interments_created']++ : $report['interments_updated']++;

        $photoSources = $existing['photo_sources'] ?? [];
        $interment = null;
        foreach ($photoHrefs as $href) {
            $photoUrl = $this->resolvePhotoUrl($href);
            if ($photoUrl === null || in_array($photoUrl, $photoSources, true)) {
                if ($photoUrl !== null) {
                    $report['photos_skipped_unchanged']++;
                }
                continue;
            }

            $localPath = $this->downloadToTemp($photoUrl);
            if ($localPath === null) {
                $report['photos_failed']++;
                $this->flagRow($report, $first, 'Photo download failed: ' . $photoUrl);
                continue;
            }

            $interment ??= $this->repository->interment($intermentId);
            $originalName = basename((string) (parse_url($photoUrl, PHP_URL_PATH) ?: 'photo.jpg'));
            $attached = $interment !== null && $this->repository->attachPhotoFromPath(
                $intermentId,
                $interment,
                $localPath,
                $originalName,
                $this->root,
                'Grave photo (public directory import)'
            );
            @unlink($localPath);

            if ($attached) {
                $report['photos_downloaded']++;
                $photoSources[] = $photoUrl;
            } else {
                $report['photos_failed']++;
                $this->flagRow($report, $first, 'Photo could not be saved: ' . $photoUrl);
            }
        }

        $state['people'][$key] = [
            'person_id' => $personId,
            'interment_id' => $intermentId,
            'photo_sources' => $photoSources,
        ];
    }

    private function sourceKey(string $surname, string $given, array $location, string $birth, string $death, bool $isUnknownIdentity, array $rows): string
    {
        if ($isUnknownIdentity) {
            $first = $rows[0];
            $material = $first['photo_href'] !== '' ? $first['photo_href'] : ($first['notes'] . '|' . $first['location_raw']);

            return sha1('unknown|' . $material);
        }

        $locationPart = $location['identifier'] ?? ($location['type'] . '|' . $location['raw']);

        return sha1($this->normalizeName($surname) . '|' . $this->normalizeName($given) . '|' . $locationPart . '|' . $birth . '|' . $death);
    }

    private function ensureBlockCustomField(): void
    {
        if ($this->blockCustomFieldEnsured) {
            return;
        }
        $this->blockCustomFieldEnsured = true;

        foreach ($this->repository->customFieldDefinitions('plot') as $definition) {
            if (($definition['field_key'] ?? '') === 'block') {
                return;
            }
        }

        $this->repository->saveCustomFieldDefinition([
            'entity_type' => 'plot',
            'label' => 'Block',
            'field_key' => 'block',
            'field_type' => 'text',
            'help_text' => 'Block number from the hand-drawn cemetery map.',
            'sort_order' => 0,
        ]);
    }

    private function resolvePhotoUrl(string $href): ?string
    {
        if ($href === '') {
            return null;
        }

        if (preg_match('#^https?://#i', $href) === 1) {
            return $href;
        }

        return rtrim($this->photoBaseUrl, '/') . (str_starts_with($href, '/') ? $href : '/' . $href);
    }

    private function downloadToTemp(string $url): ?string
    {
        $body = $this->fetchUrl($url);
        if ($body === null || $body === '') {
            return null;
        }

        $tmp = tempnam(sys_get_temp_dir(), 'anesti_photo_');
        if ($tmp === false || file_put_contents($tmp, $body) === false) {
            if ($tmp !== false) {
                @unlink($tmp);
            }
            return null;
        }

        return $tmp;
    }

    private function fetchUrl(string $url): ?string
    {
        if (!function_exists('curl_init')) {
            $context = stream_context_create(['http' => [
                'method' => 'GET',
                'timeout' => 30,
                'follow_location' => 1,
                'header' => implode("\r\n", [
                    'User-Agent: Mozilla/5.0 (compatible; AnestiDirectoryImporter/1.0; +https://github.com/tuutaal/churchyard-ledger)',
                    'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
                    'Accept-Language: en-US,en;q=0.9',
                ]),
            ]]);

            return @file_get_contents($url, false, $context) ?: null;
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; AnestiDirectoryImporter/1.0; +https://github.com/tuutaal/churchyard-ledger)',
            CURLOPT_HTTPHEADER => [
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
                'Accept-Language: en-US,en;q=0.9',
            ],
        ]);

        try {
            $body = curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        } catch (Throwable) {
            curl_close($ch);
            return null;
        }
        curl_close($ch);

        if ($body === false || $body === '' || $status >= 400) {
            return null;
        }

        return $body;
    }

    private function flagRow(array &$report, array $row, string $reason): void
    {
        $report['flagged'][] = [
            'page' => $row['_page'] ?? '',
            'row' => $row['_row'] ?? 0,
            'surname' => $row['surname'] ?? '',
            'given_name' => $row['given_name'] ?? '',
            'location_raw' => $row['location_raw'] ?? '',
            'reason' => $reason,
        ];
    }

    private function isPlaceholderName(string $value): bool
    {
        return strtolower(trim($value)) === '(unknown)';
    }

    private function normalizeName(string $value): string
    {
        return strtolower($this->cleanText($value));
    }

    private function cleanText(string $value): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $value));
    }
}
