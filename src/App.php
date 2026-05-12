<?php
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Anesti;

use Throwable;

final class App
{
    private readonly Env $env;
    private readonly Repository $repository;

    public function __construct(private readonly string $root)
    {
        $this->env = new Env($root);
        $db = (new Database($this->env->all()))->connect();
        $this->repository = new Repository($db);
    }

    public function handle(string $uri): void
    {
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        $path = preg_replace('#^/index\.php/?#', '/', $path) ?? $path;
        $route = trim($path, '/') ?: 'dashboard';

        if ($route === 'install') {
            $this->install();
            return;
        }

        $known = ['dashboard', 'people', 'plots', 'search', 'map', 'tutorial', 'public'];
        if (!in_array($route, $known, true)) {
            http_response_code(404);
            $this->layout('Not Found', 'not-found', '<div class="panel"><h1>Page not found</h1></div>');
            return;
        }

        $html = match ($route) {
            'people' => $this->people(),
            'plots' => $this->plots(),
            'search' => $this->search(),
            'map' => $this->map(),
            'tutorial' => $this->tutorial(),
            'public' => $this->publicPage(),
            default => $this->dashboard(),
        };

        $this->layout(ucfirst($route), $route, $html);
    }

    private function dashboard(): string
    {
        $cemetery = $this->repository->cemetery();
        $counts = $this->repository->counts();
        $items = $this->repository->verificationItems();

        ob_start();
        ?>
        <div class="hero-grid">
            <section class="panel">
                <p class="eyebrow">Records workspace</p>
                <h1><?= e($cemetery['name']) ?></h1>
                <p class="lede"><?= e($cemetery['description'] ?? 'A calm working view for cemetery records.') ?></p>
                <span class="badge">Showing <?= e($this->repository->source()) ?></span>
            </section>
            <section class="panel primary">
                <p class="eyebrow">Next best action</p>
                <h2>Review uncertain records</h2>
                <p class="lede">Start with records marked probable, conflicting, or unknown before publishing broader public search results.</p>
                <a class="button secondary" href="plots">Open review queue</a>
            </section>
        </div>
        <div class="metrics">
            <?= $this->metric('Cemeteries', $counts['cemeteries'], e($cemetery['name'])) ?>
            <?= $this->metric('Plots', $counts['plots'], 'Available, reserved, occupied, and review statuses') ?>
            <?= $this->metric('Interments', $counts['interments'], 'People connected to cemetery plots') ?>
            <?= $this->metric('Public records', $counts['public_records'], 'Visible on public pages') ?>
        </div>
        <div class="two-column">
            <section class="card">
                <h2>Verification Queue</h2>
                <table class="table" style="margin-top:14px">
                    <thead><tr><th>Record</th><th>Detail</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td><strong><?= e($item['record']) ?></strong></td>
                            <td><?= e($item['detail']) ?></td>
                            <td><span class="status <?= e($item['tone']) ?>"><?= e($item['status']) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </section>
            <section class="card">
                <h2>Map Readiness</h2>
                <div class="map-preview" style="margin-top:14px">
                    <div class="map-block" style="left:18%;top:20%;width:120px;height:82px">Section A</div>
                    <div class="map-block" style="right:18%;top:38%;width:138px;height:96px">Section B</div>
                </div>
            </section>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    private function people(): string
    {
        ob_start();
        ?>
        <section class="panel">
            <p class="eyebrow">People</p>
            <h1>People</h1>
            <p class="lede">Record names, partial dates, notes, confidence, and public/private visibility.</p>
            <?= $this->peopleTable($this->repository->people()) ?>
        </section>
        <?php
        return (string) ob_get_clean();
    }

    private function plots(): string
    {
        ob_start();
        ?>
        <section class="panel">
            <p class="eyebrow">Plots</p>
            <h1>Plots</h1>
            <p class="lede">Track plot identifiers, sections, status, verification confidence, and visibility.</p>
            <?= $this->plotsTable($this->repository->plots()) ?>
        </section>
        <?php
        return (string) ob_get_clean();
    }

    private function search(): string
    {
        ob_start();
        ?>
        <section class="panel">
            <p class="eyebrow">Search</p>
            <h1>Search</h1>
            <p class="lede">This first shared-hosting build lists records now. Interactive filtering comes next.</p>
            <div class="two-column">
                <div><?= $this->peopleTable($this->repository->people()) ?></div>
                <div><?= $this->plotsTable($this->repository->plots()) ?></div>
            </div>
        </section>
        <?php
        return (string) ob_get_clean();
    }

    private function map(): string
    {
        return '<section class="panel"><p class="eyebrow">Map</p><h1>Map</h1><p class="lede">GeoJSON-style plot data is in the schema so GIS support can grow later.</p><div class="map-preview"><div class="map-block" style="left:18%;top:20%;width:120px;height:82px">Section A</div><div class="map-block" style="right:18%;top:38%;width:138px;height:96px">Section B</div></div></section>';
    }

    private function tutorial(): string
    {
        $steps = [
            'Start on the dashboard to see totals and records needing verification.',
            'Open Plots to review status, confidence, and public/private visibility.',
            'Open People to review names, partial dates, and uncertain records.',
            'Use Public Pages later to share only approved public cemetery records.',
        ];

        $html = '<section class="panel"><p class="eyebrow">First demo walkthrough</p><h1>Tutorial</h1><p class="lede">A short guide for a church secretary, pastor, trustee, or volunteer opening Anesti for the first time.</p><div class="metrics">';
        foreach ($steps as $index => $step) {
            $html .= '<div class="card"><p class="card-label">Step ' . ($index + 1) . '</p><p class="card-detail">' . e($step) . '</p></div>';
        }
        return $html . '</div></section>';
    }

    private function publicPage(): string
    {
        return '<section class="panel"><p class="eyebrow">Public cemetery page</p><h1>Public Records</h1><p class="lede">Public pages should show only records marked public by the organization.</p><a class="button" href="search">Search records</a></section>';
    }

    private function install(): void
    {
        $message = '';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $values = [
                'ANESTI_DB_HOST' => $_POST['db_host'] ?? 'localhost',
                'ANESTI_DB_PORT' => $_POST['db_port'] ?? '3306',
                'ANESTI_DB_NAME' => $_POST['db_name'] ?? '',
                'ANESTI_DB_USER' => $_POST['db_user'] ?? '',
                'ANESTI_DB_PASSWORD' => $_POST['db_password'] ?? '',
                'ANESTI_APP_URL' => $_POST['app_url'] ?? '',
            ];
            $this->env->write($values);
            $db = (new Database((new Env($this->root))->all()))->connect();
            if ($db) {
                try {
                    Schema::migrate($db);
                    Seeder::seed($db);
                    $message = 'Install complete. You can open the dashboard now.';
                } catch (Throwable $error) {
                    $message = 'Database connected, but setup failed: ' . $error->getMessage();
                }
            } else {
                $message = 'Could not connect to the database. Check the database name, user, and password.';
            }
        }

        ob_start();
        ?>
        <section class="panel">
            <p class="eyebrow">Installer</p>
            <h1>Set up Anesti</h1>
            <p class="lede">Enter your cPanel MySQL details. This creates the tables and sample data.</p>
            <?php if ($message): ?><div class="notice"><?= e($message) ?></div><?php endif; ?>
            <form class="form" method="post">
                <label>Database host <input name="db_host" value="localhost" required></label>
                <label>Database port <input name="db_port" value="3306" required></label>
                <label>Database name <input name="db_name" required></label>
                <label>Database user <input name="db_user" required></label>
                <label>Database password <input name="db_password" type="password"></label>
                <label>App URL <input name="app_url" placeholder="https://records.example.org"></label>
                <button class="button" type="submit">Install sample data</button>
            </form>
        </section>
        <?php
        $this->layout('Install', 'install', (string) ob_get_clean());
    }

    private function metric(string $label, int $value, string $detail): string
    {
        return '<div class="card"><p class="card-label">' . e($label) . '</p><p class="card-value">' . e($value) . '</p><p class="card-detail">' . e($detail) . '</p></div>';
    }

    private function peopleTable(array $people): string
    {
        $html = '<table class="table" style="margin-top:14px"><thead><tr><th>Name</th><th>Born</th><th>Died</th><th>Confidence</th><th>Visibility</th></tr></thead><tbody>';
        foreach ($people as $person) {
            $html .= '<tr><td><strong>' . e($person['legal_name']) . '</strong></td><td>' . e($person['birth_date_text'] ?: 'Unknown') . '</td><td>' . e($person['death_date_text'] ?: 'Unknown') . '</td><td>' . e(pretty($person['confidence'])) . '</td><td>' . e(pretty($person['visibility'])) . '</td></tr>';
        }
        return $html . '</tbody></table>';
    }

    private function plotsTable(array $plots): string
    {
        $html = '<table class="table" style="margin-top:14px"><thead><tr><th>Plot</th><th>Section</th><th>Row</th><th>Lot</th><th>Status</th><th>Confidence</th></tr></thead><tbody>';
        foreach ($plots as $plot) {
            $html .= '<tr><td><strong>' . e($plot['identifier']) . '</strong></td><td>' . e($plot['section_code'] ?? 'None') . '</td><td>' . e($plot['row_label'] ?? 'Unknown') . '</td><td>' . e($plot['lot'] ?? 'Unknown') . '</td><td>' . e(pretty($plot['status'])) . '</td><td>' . e(pretty($plot['confidence'])) . '</td></tr>';
        }
        return $html . '</tbody></table>';
    }

    private function layout(string $title, string $route, string $content): void
    {
        $nav = ['dashboard' => 'Dashboard', 'people' => 'People', 'plots' => 'Plots', 'search' => 'Search', 'map' => 'Map', 'tutorial' => 'Tutorial', 'public' => 'Public Page', 'install' => 'Install'];
        ?>
        <!doctype html>
        <html lang="en">
        <head>
            <meta charset="utf-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title><?= e($title) ?> - Anesti</title>
            <link rel="stylesheet" href="assets/app.css">
        </head>
        <body>
        <div class="app">
            <aside class="sidebar">
                <div class="brand"><div class="brand-mark">A</div><div><p class="brand-title">Anesti</p><p class="brand-subtitle">Cemetery records and maps</p></div></div>
                <nav class="nav">
                    <?php foreach ($nav as $path => $label): ?>
                        <a href="<?= e($path === 'dashboard' ? '/' : $path) ?>"<?= active($path, $route) ?>><?= e($label) ?></a>
                    <?php endforeach; ?>
                </nav>
                <div class="support-note">Free and open-source software for small churches, rural cemeteries, and volunteer cemetery boards.</div>
            </aside>
            <main class="main">
                <header class="topbar"><p>Cemetery records, maps, public pages, and verification work</p></header>
                <div class="content"><?= $content ?></div>
            </main>
        </div>
        </body>
        </html>
        <?php
    }
}
