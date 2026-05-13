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
        $segments = $route === 'dashboard' ? ['dashboard'] : explode('/', $route);

        if ($route === 'install') {
            $this->install();
            return;
        }

        if ($segments[0] === 'people') {
            $html = match (true) {
                count($segments) === 1 => $this->people(),
                ($segments[1] ?? '') === 'new' => $this->personForm(null),
                ($segments[2] ?? '') === 'edit' => $this->personForm($segments[1]),
                default => null,
            };
            if ($html !== null) {
                $this->layout('People', 'people', $html);
                return;
            }
        }

        if ($segments[0] === 'plots') {
            $html = match (true) {
                count($segments) === 1 => $this->plots(),
                ($segments[1] ?? '') === 'new' => $this->plotForm(null),
                ($segments[2] ?? '') === 'edit' => $this->plotForm($segments[1]),
                default => null,
            };
            if ($html !== null) {
                $this->layout('Plots', 'plots', $html);
                return;
            }
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
                <a class="button secondary" href="/plots">Open review queue</a>
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
            <div class="actions"><a class="button" href="/people/new">Add person</a></div>
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
            <div class="actions"><a class="button" href="/plots/new">Add plot</a></div>
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
            'Open People and click Edit beside a sample person. Try adding a note or changing confidence to probable.',
            'Open Plots and click Edit beside a plot. Review status, visibility, row, lot, and section.',
            'Use needs verification, probable, conflicting, or unknown whenever a church record is incomplete.',
            'Keep private records private until the cemetery board is ready to publish them.',
        ];

        $html = '<section class="panel"><p class="eyebrow">First working walkthrough</p><h1>Tutorial</h1><p class="lede">Use this demo like a small cemetery office notebook: check uncertain records first, make a careful edit, then confirm what should be public.</p><div class="actions"><a class="button" href="/people">Edit people</a><a class="button secondary" href="/plots">Edit plots</a></div><div class="metrics">';
        foreach ($steps as $index => $step) {
            $html .= '<div class="card"><p class="card-label">Step ' . ($index + 1) . '</p><p class="card-detail">' . e($step) . '</p></div>';
        }
        return $html . '</div></section>';
    }

    private function publicPage(): string
    {
        return '<section class="panel"><p class="eyebrow">Public cemetery page</p><h1>Public Records</h1><p class="lede">Public pages should show only records marked public by the organization.</p><a class="button" href="/search">Search records</a></section>';
    }

    private function install(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $this->isInstalled()) {
            header('Location: /');
            return;
        }

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
                    header('Location: /?installed=1');
                    return;
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
                <label>Database host <input name="db_host" value="<?= e($this->env->all()['ANESTI_DB_HOST'] ?? 'localhost') ?>" required></label>
                <label>Database port <input name="db_port" value="<?= e($this->env->all()['ANESTI_DB_PORT'] ?? '3306') ?>" required></label>
                <label>Database name <input name="db_name" value="<?= e($this->env->all()['ANESTI_DB_NAME'] ?? '') ?>" required></label>
                <label>Database user <input name="db_user" value="<?= e($this->env->all()['ANESTI_DB_USER'] ?? '') ?>" required></label>
                <label>Database password <input name="db_password" type="password"></label>
                <label>App URL <input name="app_url" value="<?= e($this->env->all()['ANESTI_APP_URL'] ?? '') ?>" placeholder="https://records.example.org"></label>
                <button class="button" type="submit">Install sample data</button>
            </form>
        </section>
        <?php
        $this->layout('Install', 'install', (string) ob_get_clean());
    }

    private function isInstalled(): bool
    {
        $db = (new Database($this->env->all()))->connect();
        if ($db === null) {
            return false;
        }

        try {
            $db->query('select 1 from cemeteries limit 1');
            return true;
        } catch (Throwable) {
            return false;
        }
    }

    private function metric(string $label, int $value, string $detail): string
    {
        return '<div class="card"><p class="card-label">' . e($label) . '</p><p class="card-value">' . e($value) . '</p><p class="card-detail">' . e($detail) . '</p></div>';
    }

    private function personForm(?string $id): string
    {
        $person = $id === null ? [] : $this->repository->person($id);
        if ($id !== null && !$person) {
            http_response_code(404);
            return '<section class="panel"><h1>Person not found</h1><p class="lede">That person record could not be found.</p></section>';
        }

        $message = '';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $savedId = $this->repository->savePerson($id, $_POST);
            if ($savedId !== null) {
                header('Location: /people/' . rawurlencode($savedId) . '/edit?saved=1');
                return '';
            }
            $message = 'Could not save the person record. Check that the database is connected.';
            $person = $_POST;
        } elseif (($_GET['saved'] ?? '') === '1') {
            $message = 'Person record saved.';
        }

        ob_start();
        ?>
        <section class="panel">
            <p class="eyebrow">People</p>
            <h1><?= $id === null ? 'Add person' : 'Edit person' ?></h1>
            <?php if ($message): ?><div class="notice"><?= e($message) ?></div><?php endif; ?>
            <form class="form record-form" method="post">
                <label>Legal name <input name="legal_name" value="<?= e($person['legal_name'] ?? '') ?>" required></label>
                <label>Given name <input name="given_name" value="<?= e($person['given_name'] ?? '') ?>"></label>
                <label>Family name <input name="family_name" value="<?= e($person['family_name'] ?? '') ?>"></label>
                <label>Maiden name <input name="maiden_name" value="<?= e($person['maiden_name'] ?? '') ?>"></label>
                <label>Birth date text <input name="birth_date_text" value="<?= e($person['birth_date_text'] ?? '') ?>" placeholder="1881 or about 1881"></label>
                <label>Death date text <input name="death_date_text" value="<?= e($person['death_date_text'] ?? '') ?>" placeholder="June 1946 or unknown"></label>
                <label>Alternate names <input name="alternate_names_text" value="<?= e($person['alternate_names_text'] ?? '') ?>" placeholder="Separate names with commas"></label>
                <?= $this->select('confidence', 'Confidence', $person['confidence'] ?? 'unknown', ['confirmed', 'probable', 'conflicting', 'unknown']) ?>
                <?= $this->select('visibility', 'Visibility', $person['visibility'] ?? 'private', ['private', 'public']) ?>
                <label class="full">Notes <textarea name="notes" rows="5"><?= e($person['notes'] ?? '') ?></textarea></label>
                <div class="actions full">
                    <button class="button" type="submit">Save person</button>
                    <a class="button secondary" href="/people">Back to people</a>
                </div>
            </form>
        </section>
        <?php
        return (string) ob_get_clean();
    }

    private function plotForm(?string $id): string
    {
        $plot = $id === null ? [] : $this->repository->plot($id);
        if ($id !== null && !$plot) {
            http_response_code(404);
            return '<section class="panel"><h1>Plot not found</h1><p class="lede">That plot record could not be found.</p></section>';
        }

        $message = '';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $savedId = $this->repository->savePlot($id, $_POST);
            if ($savedId !== null) {
                header('Location: /plots/' . rawurlencode($savedId) . '/edit?saved=1');
                return '';
            }
            $message = 'Could not save the plot record. Check that the database is connected.';
            $plot = $_POST;
        } elseif (($_GET['saved'] ?? '') === '1') {
            $message = 'Plot record saved.';
        }

        ob_start();
        ?>
        <section class="panel">
            <p class="eyebrow">Plots</p>
            <h1><?= $id === null ? 'Add plot' : 'Edit plot' ?></h1>
            <?php if ($message): ?><div class="notice"><?= e($message) ?></div><?php endif; ?>
            <form class="form record-form" method="post">
                <label>Plot identifier <input name="identifier" value="<?= e($plot['identifier'] ?? '') ?>" required></label>
                <label>Section
                    <select name="section_id">
                        <option value="">No section</option>
                        <?php foreach ($this->repository->sections() as $section): ?>
                            <option value="<?= e($section['id']) ?>"<?= ($plot['section_id'] ?? '') === $section['id'] ? ' selected' : '' ?>><?= e($section['code'] . ' - ' . $section['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Row <input name="row_label" value="<?= e($plot['row_label'] ?? '') ?>"></label>
                <label>Lot <input name="lot" value="<?= e($plot['lot'] ?? '') ?>"></label>
                <?= $this->select('status', 'Status', $plot['status'] ?? 'unknown', ['available', 'reserved', 'occupied', 'sold', 'unknown', 'unusable', 'needs_verification']) ?>
                <?= $this->select('confidence', 'Confidence', $plot['confidence'] ?? 'unknown', ['confirmed', 'probable', 'conflicting', 'unknown']) ?>
                <?= $this->select('visibility', 'Visibility', $plot['visibility'] ?? 'private', ['private', 'public']) ?>
                <label class="full">Notes <textarea name="notes" rows="5"><?= e($plot['notes'] ?? '') ?></textarea></label>
                <div class="actions full">
                    <button class="button" type="submit">Save plot</button>
                    <a class="button secondary" href="/plots">Back to plots</a>
                </div>
            </form>
        </section>
        <?php
        return (string) ob_get_clean();
    }

    private function select(string $name, string $label, string $selected, array $options): string
    {
        $html = '<label>' . e($label) . '<select name="' . e($name) . '">';
        foreach ($options as $option) {
            $html .= '<option value="' . e($option) . '"' . ($selected === $option ? ' selected' : '') . '>' . e(pretty($option)) . '</option>';
        }
        return $html . '</select></label>';
    }

    private function peopleTable(array $people): string
    {
        $html = '<table class="table" style="margin-top:14px"><thead><tr><th>Name</th><th>Born</th><th>Died</th><th>Confidence</th><th>Visibility</th><th></th></tr></thead><tbody>';
        foreach ($people as $person) {
            $html .= '<tr><td><strong>' . e($person['legal_name']) . '</strong></td><td>' . e($person['birth_date_text'] ?: 'Unknown') . '</td><td>' . e($person['death_date_text'] ?: 'Unknown') . '</td><td>' . e(pretty($person['confidence'])) . '</td><td>' . e(pretty($person['visibility'])) . '</td><td><a class="table-action" href="/people/' . e($person['id']) . '/edit">Edit</a></td></tr>';
        }
        return $html . '</tbody></table>';
    }

    private function plotsTable(array $plots): string
    {
        $html = '<table class="table" style="margin-top:14px"><thead><tr><th>Plot</th><th>Section</th><th>Row</th><th>Lot</th><th>Status</th><th>Confidence</th><th></th></tr></thead><tbody>';
        foreach ($plots as $plot) {
            $html .= '<tr><td><strong>' . e($plot['identifier']) . '</strong></td><td>' . e($plot['section_code'] ?? 'None') . '</td><td>' . e($plot['row_label'] ?? 'Unknown') . '</td><td>' . e($plot['lot'] ?? 'Unknown') . '</td><td>' . e(pretty($plot['status'])) . '</td><td>' . e(pretty($plot['confidence'])) . '</td><td><a class="table-action" href="/plots/' . e($plot['id']) . '/edit">Edit</a></td></tr>';
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
            <link rel="stylesheet" href="/assets/app.css">
        </head>
        <body>
        <div class="app">
            <aside class="sidebar">
                <div class="brand"><div class="brand-mark">A</div><div><p class="brand-title">Anesti</p><p class="brand-subtitle">Cemetery records and maps</p></div></div>
                <nav class="nav">
                    <?php foreach ($nav as $path => $label): ?>
                        <a href="<?= e($path === 'dashboard' ? '/' : '/' . $path) ?>"<?= active($path, $route) ?>><?= e($label) ?></a>
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
