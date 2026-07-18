#!/usr/bin/env php
<?php
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This script only runs from the command line.');
}

$root = dirname(__DIR__);
require_once $root . '/src/bootstrap.php';

use Anesti\Database;
use Anesti\Env;
use Anesti\PublicDirectoryImport;
use Anesti\Repository;
use Anesti\Schema;

$options = getopt('', ['dry-run', 'input-dir:', 'visibility:', 'state:', 'report:', 'config:', 'help']);

if (array_key_exists('help', $options)) {
    fwrite(STDOUT, <<<'TXT'
    Import a public cemetery directory (a set of WordPress table pages) into Anesti.

    This is not tied to any one church's site - point --config at a JSON file
    describing your own pages. See scripts/directory-import.config.example.json
    for the format and copy it to a real config (gitignored) with your church's URLs.

    Usage: php scripts/import_public_directory.php [options]

      --config=PATH        Path to the JSON config with "pages" and "photo_base_url"
                            (default: scripts/directory-import.config.json).
      --dry-run           Parse and report only; no database writes, no state file changes.
      --input-dir=PATH    Read PATH/{label}.html instead of fetching from the live site,
                           one file per key in the config's "pages" object. Useful for testing.
      --visibility=public|private
                           Visibility to set on newly created records (default: public).
      --state=PATH         Path to the idempotency state file (default: scripts/state/public-directory-import.json).
      --report=PATH        Also write the full JSON report to PATH.
      --help                Show this help.

    Re-running is safe: existing people/plots/interments are matched via the state
    file and updated in place rather than duplicated.

    TXT);
    exit(0);
}

$dryRun = array_key_exists('dry-run', $options);
$inputDir = isset($options['input-dir']) ? (string) $options['input-dir'] : null;
$visibility = isset($options['visibility']) ? (string) $options['visibility'] : 'public';
if (!in_array($visibility, ['public', 'private'], true)) {
    fwrite(STDERR, "Invalid --visibility value. Use 'public' or 'private'.\n");
    exit(1);
}
$statePath = isset($options['state']) ? (string) $options['state'] : $root . '/scripts/state/public-directory-import.json';
$reportPath = isset($options['report']) ? (string) $options['report'] : null;
$configPath = isset($options['config']) ? (string) $options['config'] : $root . '/scripts/directory-import.config.json';

if (!is_file($configPath)) {
    fwrite(STDERR, "Config file not found: $configPath\n");
    fwrite(STDERR, "Copy scripts/directory-import.config.example.json to $configPath and fill in your church's page URLs.\n");
    exit(1);
}
$config = json_decode((string) file_get_contents($configPath), true);
if (!is_array($config) || empty($config['pages']) || !is_array($config['pages']) || empty($config['photo_base_url'])) {
    fwrite(STDERR, "Config file $configPath must have a non-empty \"pages\" object and a \"photo_base_url\".\n");
    exit(1);
}
$pageUrls = $config['pages'];
$photoBaseUrl = (string) $config['photo_base_url'];

$env = new Env($root);
$db = (new Database($env->all()))->connect();
if ($db === null) {
    fwrite(STDERR, "Could not connect to the database. Check .env in $root.\n");
    exit(1);
}

Schema::migrate($db);
$repository = new Repository($db);
$importer = new PublicDirectoryImport($repository, $root, $photoBaseUrl);

$state = [];
if (is_file($statePath)) {
    $decoded = json_decode((string) file_get_contents($statePath), true);
    $state = is_array($decoded) ? $decoded : [];
}
$state += ['people' => []];

$pages = [];
foreach ($pageUrls as $label => $url) {
    $label = (string) $label;
    $url = (string) $url;
    if ($inputDir !== null) {
        $path = rtrim($inputDir, '/\\') . '/' . $label . '.html';
        if (!is_file($path)) {
            fwrite(STDERR, "Missing local fixture for \"$label\": $path\n");
            exit(1);
        }
        $html = file_get_contents($path);
        if ($html === false) {
            fwrite(STDERR, "Could not read $path\n");
            exit(1);
        }
    } else {
        fwrite(STDERR, "Fetching $label ($url)...\n");
        $html = $importer->fetchPage($url);
        if ($html === null) {
            fwrite(STDERR, "Failed to fetch $label from $url\n");
            exit(1);
        }
    }
    $pages[$label] = $html;
}

$report = $importer->run($pages, $state, ['visibility' => $visibility, 'dry_run' => $dryRun]);

if (!$dryRun) {
    $stateDir = dirname($statePath);
    if (!is_dir($stateDir) && !mkdir($stateDir, 0755, true) && !is_dir($stateDir)) {
        fwrite(STDERR, "Could not create state directory $stateDir\n");
        exit(1);
    }
    file_put_contents($statePath, json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}

fwrite(STDOUT, "\n=== Import summary ===\n");
foreach ($report as $key => $value) {
    if ($key === 'flagged') {
        continue;
    }
    fwrite(STDOUT, str_pad((string) $key, 28) . ': ' . $value . "\n");
}

if ($report['flagged']) {
    fwrite(STDOUT, "\n=== Flagged rows (" . count($report['flagged']) . ") ===\n");
    foreach ($report['flagged'] as $item) {
        fwrite(STDOUT, sprintf(
            "[%s row %d] %s %s - %s\n",
            $item['page'],
            $item['row'],
            $item['surname'],
            $item['given_name'],
            $item['reason']
        ));
    }
}

if ($reportPath !== null) {
    if (file_put_contents($reportPath, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) !== false) {
        fwrite(STDOUT, "\nFull report written to $reportPath\n");
    } else {
        fwrite(STDERR, "\nCould not write report to $reportPath\n");
    }
}

if ($dryRun) {
    fwrite(STDOUT, "\n(dry run - no changes were saved)\n");
}
