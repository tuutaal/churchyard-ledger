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
        if ($db !== null) {
            try {
                Schema::migrate($db);
            } catch (Throwable) {
            }
        }
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

        if ($segments[0] === 'interments') {
            $html = match (true) {
                count($segments) === 1 => $this->interments(),
                ($segments[1] ?? '') === 'new' => $this->intermentForm(null),
                ($segments[2] ?? '') === 'edit' => $this->intermentForm($segments[1]),
                default => null,
            };
            if ($html !== null) {
                $this->layout('Interments', 'interments', $html);
                return;
            }
        }

        if ($segments[0] === 'exports') {
            if (count($segments) === 1) {
                $this->layout('Exports', 'exports', $this->exports());
                return;
            }

            $this->downloadCsv($segments[1]);
            return;
        }

        $known = ['dashboard', 'people', 'plots', 'interments', 'search', 'map', 'tutorial', 'public', 'imports', 'exports', 'custom-fields'];
        if (!in_array($route, $known, true)) {
            http_response_code(404);
            $this->layout('Not Found', 'not-found', '<div class="panel"><h1>Page not found</h1></div>');
            return;
        }

        $html = match ($route) {
            'people' => $this->people(),
            'plots' => $this->plots(),
            'interments' => $this->interments(),
            'search' => $this->search(),
            'map' => $this->map(),
            'tutorial' => $this->tutorial(),
            'public' => $this->publicPage(),
            'imports' => $this->imports(),
            'exports' => $this->exports(),
            'custom-fields' => $this->customFields(),
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

    private function interments(): string
    {
        ob_start();
        ?>
        <section class="panel">
            <p class="eyebrow">Interments</p>
            <h1>Interments</h1>
            <p class="lede">Connect people to plots. A plot can have one interment, no interments, or multiple interments for double burials and cremains.</p>
            <div class="actions"><a class="button" href="/interments/new">Add interment</a></div>
            <?= $this->intermentsTable($this->repository->interments()) ?>
        </section>
        <?php
        return (string) ob_get_clean();
    }

    private function search(): string
    {
        $query = trim((string) ($_GET['q'] ?? ''));
        if (strlen($query) > 120) {
            $query = substr($query, 0, 120);
        }

        $people = $this->repository->people($query);
        $plots = $this->repository->plots($query);
        $interments = $this->repository->interments($query);
        $resultCount = count($people) + count($plots) + count($interments);

        ob_start();
        ?>
        <section class="panel">
            <p class="eyebrow">Admin and editor search</p>
            <h1>Internal Search</h1>
            <p class="lede">Search all records, including private records and working notes. This is for administrators and editors, not the public cemetery page.</p>
            <form class="search-form" method="get" action="/search">
                <label for="admin-search">Search records</label>
                <div class="search-row">
                    <input id="admin-search" name="q" value="<?= e($query) ?>" placeholder="Name, plot, section, status, year, or marker text">
                    <button class="button" type="submit">Search</button>
                    <?php if ($query !== ''): ?><a class="button secondary" href="/search">Clear</a><?php endif; ?>
                </div>
            </form>
            <?php if ($query !== ''): ?>
                <p class="search-summary"><?= e($resultCount) ?> internal <?= $resultCount === 1 ? 'result' : 'results' ?> for "<?= e($query) ?>"</p>
            <?php endif; ?>
            <div class="actions"><a class="button secondary" href="/interments">View interments</a></div>
        </section>
        <section class="card public-section">
            <h2>People</h2>
            <?= $this->peopleTable($people) ?>
        </section>
        <section class="card public-section">
            <h2>Plots</h2>
            <?= $this->plotsTable($plots) ?>
        </section>
        <section class="card public-section">
            <h2>Interments</h2>
            <?= $this->intermentsTable($interments) ?>
        </section>
        <?php
        return (string) ob_get_clean();
    }

    private function map(): string
    {
        $plots = $this->repository->mapPlots();
        $statusCounts = [];
        foreach ($plots as $plot) {
            $status = (string) ($plot['status'] ?? 'unknown');
            $statusCounts[$status] = ($statusCounts[$status] ?? 0) + 1;
        }

        $statuses = ['available', 'reserved', 'occupied', 'sold', 'needs_verification', 'unusable', 'unknown'];
        $mapData = $this->mapFeatures($plots);
        $mapJson = json_encode($mapData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);

        ob_start();
        ?>
        <section class="gis-map-shell" data-map-plots="<?= e((string) $mapJson) ?>">
            <div class="gis-toolbar">
                <div class="gis-search">
                    <button class="gis-dropdown" type="button" aria-label="Map search options">⌄</button>
                    <input id="map-search" type="search" placeholder="Find plot, name, row, or lot">
                    <button class="gis-search-button" type="button" aria-label="Search map">Search</button>
                </div>
                <div class="gis-status-filters" aria-label="Plot status filters">
                    <?php foreach ($statuses as $status): ?>
                        <?php if (($statusCounts[$status] ?? 0) > 0): ?>
                            <label><input type="checkbox" value="<?= e($status) ?>" checked><span class="plot-key status-<?= e($status) ?>"></span><?= e(pretty($status)) ?></label>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="gis-controls" aria-label="Map controls">
                <button type="button" data-map-action="zoom-in" aria-label="Zoom in">+</button>
                <button type="button" data-map-action="zoom-out" aria-label="Zoom out">−</button>
                <button type="button" data-map-action="home" aria-label="Reset map">⌂</button>
            </div>
            <div class="gis-map-stage">
                <?php if (!$plots): ?>
                    <div class="gis-empty">
                        <h1>No plots yet</h1>
                        <p>Add plots first, then the map will display them here.</p>
                        <a class="button" href="/plots/new">Add plot</a>
                    </div>
                <?php endif; ?>
                <svg class="gis-map-svg" viewBox="0 0 1600 1000" role="img" aria-label="Interactive cemetery plot map">
                    <defs>
                        <pattern id="mapGrass" width="80" height="80" patternUnits="userSpaceOnUse">
                            <rect width="80" height="80" fill="#dbe4d6"></rect>
                            <path d="M0 18 H80 M0 55 H80 M22 0 V80 M62 0 V80" stroke="#c7d3c3" stroke-width="1" opacity="0.45"></path>
                        </pattern>
                        <filter id="labelShadow" x="-20%" y="-20%" width="140%" height="140%">
                            <feDropShadow dx="0" dy="1" stdDeviation="1" flood-color="#ffffff" flood-opacity="0.9"></feDropShadow>
                        </filter>
                    </defs>
                    <rect class="gis-ground" x="0" y="0" width="1600" height="1000"></rect>
                    <path class="gis-path" d="M-40 650 C250 570 470 545 730 590 S1240 720 1680 610"></path>
                    <path class="gis-path narrow" d="M120 140 C360 250 570 260 760 190 S1120 90 1510 150"></path>
                    <g class="gis-map-content"></g>
                </svg>
                <div class="gis-attribution">Anesti map view</div>
            </div>
            <aside class="gis-details" aria-live="polite">
                <p class="card-label">Selected plot</p>
                <h2>Select a plot</h2>
                <p class="card-detail">Click a plot boundary to view details and open the plot record.</p>
            </aside>
        </section>
        <script>
        (() => {
            const shell = document.querySelector('.gis-map-shell');
            if (!shell) return;
            const plots = JSON.parse(shell.dataset.mapPlots || '[]');
            const svg = shell.querySelector('.gis-map-svg');
            const content = shell.querySelector('.gis-map-content');
            const details = shell.querySelector('.gis-details');
            const search = shell.querySelector('#map-search');
            const filters = Array.from(shell.querySelectorAll('.gis-status-filters input'));
            const ns = 'http://www.w3.org/2000/svg';
            let viewBox = { x: 0, y: 0, w: 1600, h: 1000 };
            let selectedId = '';
            let isDragging = false;
            let dragStart = null;

            function setViewBox() {
                svg.setAttribute('viewBox', `${viewBox.x} ${viewBox.y} ${viewBox.w} ${viewBox.h}`);
            }

            function zoom(multiplier) {
                const cx = viewBox.x + viewBox.w / 2;
                const cy = viewBox.y + viewBox.h / 2;
                viewBox.w = Math.max(220, Math.min(2200, viewBox.w * multiplier));
                viewBox.h = Math.max(140, Math.min(1400, viewBox.h * multiplier));
                viewBox.x = cx - viewBox.w / 2;
                viewBox.y = cy - viewBox.h / 2;
                setViewBox();
            }

            function resetView() {
                viewBox = plotExtent();
                setViewBox();
            }

            function plotExtent() {
                if (!plots.length) return { x: 0, y: 0, w: 1600, h: 1000 };
                const xs = [];
                const ys = [];
                plots.forEach((plot) => {
                    plot.points.forEach((point) => {
                        xs.push(point[0]);
                        ys.push(point[1]);
                    });
                });
                const minX = Math.min(...xs);
                const maxX = Math.max(...xs);
                const minY = Math.min(...ys);
                const maxY = Math.max(...ys);
                const padding = 120;
                const width = Math.max(520, maxX - minX + padding * 2);
                const height = Math.max(320, maxY - minY + padding * 2);

                return {
                    x: Math.max(0, minX - padding),
                    y: Math.max(0, minY - padding),
                    w: width,
                    h: height
                };
            }

            function mapPoint(event) {
                const point = svg.createSVGPoint();
                point.x = event.clientX;
                point.y = event.clientY;
                return point.matrixTransform(svg.getScreenCTM().inverse());
            }

            function polygonPoints(plot) {
                return plot.points.map((point) => `${point[0]},${point[1]}`).join(' ');
            }

            function plotMatches(plot, term, enabled) {
                if (!enabled.has(plot.status)) return false;
                if (!term) return true;
                return [
                    plot.identifier,
                    plot.section,
                    plot.row,
                    plot.lot,
                    plot.statusLabel,
                    plot.names
                ].join(' ').toLowerCase().includes(term);
            }

            function selectPlot(plot) {
                selectedId = plot.id;
                details.innerHTML = `
                    <p class="card-label">Selected plot</p>
                    <h2>${escapeHtml(plot.identifier)}</h2>
                    <p class="card-detail">${escapeHtml(plot.section || 'Unsectioned')} · ${escapeHtml(plot.statusLabel)}</p>
                    <dl class="plot-detail-list">
                        <div><dt>Location</dt><dd>${escapeHtml(plot.location || 'Not set')}</dd></div>
                        <div><dt>Interments</dt><dd>${escapeHtml(plot.names || 'None recorded')}</dd></div>
                        <div><dt>Visibility</dt><dd>${escapeHtml(plot.visibilityLabel)}</dd></div>
                        <div><dt>Confidence</dt><dd>${escapeHtml(plot.confidenceLabel)}</dd></div>
                    </dl>
                    <div class="actions"><a class="button" href="/plots/${encodeURIComponent(plot.id)}/edit">Open plot record</a></div>
                `;
                render();
            }

            function escapeHtml(value) {
                return String(value).replace(/[&<>"']/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[char]));
            }

            function render() {
                const term = (search.value || '').trim().toLowerCase();
                const enabled = new Set(filters.filter((filter) => filter.checked).map((filter) => filter.value));
                content.innerHTML = '';
                const sections = new Set();
                plots.forEach((plot) => {
                    if (plot.section && !sections.has(plot.section)) {
                        sections.add(plot.section);
                        const label = document.createElementNS(ns, 'text');
                        label.setAttribute('x', plot.sectionLabelX);
                        label.setAttribute('y', plot.sectionLabelY);
                        label.setAttribute('class', 'gis-section-label');
                        label.textContent = plot.section;
                        content.appendChild(label);
                    }
                    const visible = plotMatches(plot, term, enabled);
                    const group = document.createElementNS(ns, 'g');
                    group.setAttribute('class', `gis-plot status-${plot.status}${selectedId === plot.id ? ' selected' : ''}${visible ? '' : ' hidden'}`);
                    group.setAttribute('tabindex', '0');
                    group.setAttribute('role', 'link');
                    group.setAttribute('aria-label', `${plot.identifier} ${plot.statusLabel}`);
                    const polygon = document.createElementNS(ns, 'polygon');
                    polygon.setAttribute('points', polygonPoints(plot));
                    const label = document.createElementNS(ns, 'text');
                    label.setAttribute('x', plot.labelX);
                    label.setAttribute('y', plot.labelY);
                    label.setAttribute('class', 'gis-plot-label');
                    label.textContent = plot.identifier;
                    const sublabel = document.createElementNS(ns, 'text');
                    sublabel.setAttribute('x', plot.labelX);
                    sublabel.setAttribute('y', plot.labelY + 18);
                    sublabel.setAttribute('class', 'gis-plot-sublabel');
                    sublabel.textContent = plot.lot ? `Lot ${plot.lot}` : plot.statusLabel;
                    group.appendChild(polygon);
                    group.appendChild(label);
                    group.appendChild(sublabel);
                    group.addEventListener('click', () => selectPlot(plot));
                    group.addEventListener('keydown', (event) => {
                        if (event.key === 'Enter' || event.key === ' ') {
                            event.preventDefault();
                            selectPlot(plot);
                        }
                    });
                    content.appendChild(group);
                });
            }

            shell.querySelector('[data-map-action="zoom-in"]').addEventListener('click', () => zoom(0.78));
            shell.querySelector('[data-map-action="zoom-out"]').addEventListener('click', () => zoom(1.28));
            shell.querySelector('[data-map-action="home"]').addEventListener('click', resetView);
            search.addEventListener('input', render);
            filters.forEach((filter) => filter.addEventListener('change', render));
            svg.addEventListener('wheel', (event) => {
                event.preventDefault();
                zoom(event.deltaY < 0 ? 0.88 : 1.14);
            }, { passive: false });
            svg.addEventListener('pointerdown', (event) => {
                if (event.target.closest('.gis-plot')) return;
                isDragging = true;
                dragStart = mapPoint(event);
                svg.setPointerCapture(event.pointerId);
                svg.classList.add('dragging');
            });
            svg.addEventListener('pointermove', (event) => {
                if (!isDragging || !dragStart) return;
                const current = mapPoint(event);
                viewBox.x += dragStart.x - current.x;
                viewBox.y += dragStart.y - current.y;
                setViewBox();
            });
            svg.addEventListener('pointerup', (event) => {
                isDragging = false;
                dragStart = null;
                svg.releasePointerCapture(event.pointerId);
                svg.classList.remove('dragging');
            });

            resetView();
            render();
        })();
        </script>
        <?php
        return (string) ob_get_clean();
    }

    private function mapFeatures(array $plots): array
    {
        $sections = [];
        foreach ($plots as $plot) {
            $section = trim((string) ($plot['section_code'] ?? '')) !== '' ? 'Section ' . $plot['section_code'] : 'Unsectioned';
            $sections[$section][] = $plot;
        }

        $features = [];
        $sectionIndex = 0;
        foreach ($sections as $section => $sectionPlots) {
            $baseX = 140 + ($sectionIndex % 2) * 720;
            $baseY = 140 + intdiv($sectionIndex, 2) * 360;
            $sectionIndex++;
            foreach (array_values($sectionPlots) as $index => $plot) {
                [$x, $y, $width, $height] = $this->plotBounds($plot, $index, $baseX, $baseY);
                $names = trim((string) ($plot['interment_names'] ?? ''));
                $location = array_filter([
                    ($plot['row_label'] ?? '') !== '' ? 'Row ' . $plot['row_label'] : '',
                    ($plot['lot'] ?? '') !== '' ? 'Lot ' . $plot['lot'] : '',
                ]);
                $status = (string) ($plot['status'] ?? 'unknown');
                $features[] = [
                    'id' => (string) $plot['id'],
                    'identifier' => (string) $plot['identifier'],
                    'section' => $section,
                    'sectionLabelX' => $baseX,
                    'sectionLabelY' => $baseY - 24,
                    'row' => (string) ($plot['row_label'] ?? ''),
                    'lot' => (string) ($plot['lot'] ?? ''),
                    'location' => $location ? implode(' / ', $location) : '',
                    'status' => $status,
                    'statusLabel' => pretty($status),
                    'visibilityLabel' => pretty((string) ($plot['visibility'] ?? 'unknown')),
                    'confidenceLabel' => pretty((string) ($plot['confidence'] ?? 'unknown')),
                    'names' => $names,
                    'labelX' => $x + 8,
                    'labelY' => $y + 22,
                    'points' => [
                        [$x, $y],
                        [$x + $width, $y + $this->plotSlant($index)],
                        [$x + $width - 10, $y + $height],
                        [$x - 6, $y + $height - $this->plotSlant($index)],
                    ],
                ];
            }
        }

        return $features;
    }

    private function plotBounds(array $plot, int $index, int $baseX, int $baseY): array
    {
        $geometry = json_decode((string) ($plot['geometry'] ?? ''), true);
        if (is_array($geometry) && isset($geometry['bounds']) && is_array($geometry['bounds']) && count($geometry['bounds']) === 4) {
            return array_map('intval', $geometry['bounds']);
        }

        $row = $this->mapOrdinal((string) ($plot['row_label'] ?? ''), intdiv($index, 8) + 1);
        $lot = $this->mapOrdinal((string) ($plot['lot'] ?? ''), ($index % 8) + 1);
        $x = $baseX + (($lot - 1) % 8) * 78;
        $y = $baseY + (($row - 1) % 4) * 110 + intdiv($lot - 1, 8) * 110;

        return [$x, $y, 68, 96];
    }

    private function mapOrdinal(string $value, int $fallback): int
    {
        if (preg_match('/\d+/', $value, $matches)) {
            return max(1, (int) $matches[0]);
        }

        return $fallback;
    }

    private function plotSlant(int $index): int
    {
        return ($index % 3) * 4;
    }

    private function tutorial(): string
    {
        $steps = [
            'Create or edit a person record first. Use partial dates when the marker or old register is incomplete.',
            'Create or edit the plot. Mark its status and keep uncertainty visible with the confidence field.',
            'Add an interment to connect the person to the plot. Add another interment to the same plot for double burials or cremains.',
            'Attach a grave photo URL to the interment when you have one, then decide whether the record is public or private.',
        ];

        $html = '<section class="panel"><p class="eyebrow">First working walkthrough</p><h1>Tutorial</h1><p class="lede">Use Anesti like a careful cemetery office notebook: people, plots, and interments are separate so one plot can hold multiple burials without losing accuracy.</p><div class="actions"><a class="button" href="/people">Edit people</a><a class="button secondary" href="/plots">Edit plots</a><a class="button secondary" href="/interments">Connect interments</a></div><div class="metrics">';
        foreach ($steps as $index => $step) {
            $html .= '<div class="card"><p class="card-label">Step ' . ($index + 1) . '</p><p class="card-detail">' . e($step) . '</p></div>';
        }
        return $html . '</div></section>';
    }

    private function publicPage(): string
    {
        $cemetery = $this->repository->cemetery();
        $query = trim((string) ($_GET['q'] ?? ''));
        if (strlen($query) > 120) {
            $query = substr($query, 0, 120);
        }
        $publicInterments = $this->repository->publicInterments($query);
        $resultCount = count($publicInterments);

        ob_start();
        ?>
        <section class="panel public-hero">
            <p class="eyebrow">Public cemetery page</p>
            <h1><?= e($cemetery['name']) ?></h1>
            <p class="lede">Search public interment records. Private notes, disposition type, private plots, and private people are kept out of this view.</p>
            <form class="search-form" method="get" action="/public">
                <label for="public-search">Search public interments</label>
                <div class="search-row">
                    <input id="public-search" name="q" value="<?= e($query) ?>" placeholder="Name, date, or marker text">
                    <button class="button" type="submit">Search</button>
                    <?php if ($query !== ''): ?><a class="button secondary" href="/public">Clear</a><?php endif; ?>
                </div>
            </form>
            <?php if ($query !== ''): ?>
                <p class="search-summary"><?= e($resultCount) ?> public interment <?= $resultCount === 1 ? 'result' : 'results' ?> for "<?= e($query) ?>"</p>
            <?php endif; ?>
        </section>
        <section class="card public-section">
            <h2>Public Interments</h2>
            <?= $this->publicIntermentsTable($publicInterments, $query) ?>
        </section>
        <?php
        return (string) ob_get_clean();
    }

    private function exports(): string
    {
        $exports = [
            ['label' => 'People CSV', 'href' => '/exports/people.csv', 'detail' => 'Names, partial dates, visibility, confidence, and notes.'],
            ['label' => 'Plots CSV', 'href' => '/exports/plots.csv', 'detail' => 'Plot identifiers, sections, status, visibility, confidence, and notes.'],
            ['label' => 'Interments CSV', 'href' => '/exports/interments.csv', 'detail' => 'The person-to-plot connections, including casket/cremains, marker text, and dates.'],
            ['label' => 'Media CSV', 'href' => '/exports/media.csv', 'detail' => 'Photo and media URLs with their linked person, plot, and interment IDs.'],
        ];

        ob_start();
        ?>
        <section class="panel">
            <p class="eyebrow">Export and backup</p>
            <h1>Exports</h1>
            <p class="lede">Download plain CSV files so your cemetery records are portable and not locked into Anesti.</p>
            <div class="metrics export-grid">
                <?php foreach ($exports as $export): ?>
                    <div class="card">
                        <p class="card-label"><?= e($export['label']) ?></p>
                        <p class="card-detail"><?= e($export['detail']) ?></p>
                        <div class="actions"><a class="button" href="<?= e($export['href']) ?>">Download</a></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
        <?php
        return (string) ob_get_clean();
    }

    private function imports(): string
    {
        $result = null;
        $message = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $type = (string) ($_POST['import_type'] ?? '');
            $file = $_FILES['csv_file'] ?? null;

            if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                $message = 'Choose a CSV file to import.';
            } elseif (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
                $message = 'The CSV upload did not complete. Try again with a smaller file.';
            } elseif ((int) ($file['size'] ?? 0) > 2 * 1024 * 1024) {
                $message = 'CSV imports are limited to 2 MB for now.';
            } else {
                $path = (string) ($file['tmp_name'] ?? '');
                $result = $this->repository->importCsv($type, $path);
                $message = $result['message'];
            }
        }

        $templates = [
            'people' => 'legal_name,given_name,family_name,maiden_name,birth_date_text,death_date_text,alternate_names,visibility,confidence,notes',
            'plots' => 'identifier,section_code,row_label,lot,status,visibility,confidence,notes',
            'interments' => 'person_name,plot_identifier,disposition_type,interment_date_text,burial_permit_number,plot_position,marker_transcription,visibility,confidence,notes,photo_url',
        ];

        ob_start();
        ?>
        <section class="panel">
            <p class="eyebrow">Import</p>
            <h1>Import CSV Records</h1>
            <p class="lede">Bring in people, plots, or interments from a simple CSV file. Start with a small file and review the imported records before publishing them.</p>
            <?php if ($message): ?><div class="notice"><?= e($message) ?></div><?php endif; ?>
            <?php if ($result !== null): ?>
                <div class="metrics import-summary">
                    <?= $this->metric('Imported', (int) $result['imported'], 'Rows saved to Anesti') ?>
                    <?= $this->metric('Skipped', (int) $result['skipped'], 'Rows left unchanged because required data was missing or invalid') ?>
                    <?= $this->metric('Processed', (int) $result['processed'], 'CSV data rows read') ?>
                </div>
                <?php if ($result['errors']): ?>
                    <div class="card import-errors">
                        <h2>Rows To Review</h2>
                        <ul>
                            <?php foreach ($result['errors'] as $error): ?>
                                <li><?= e($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
            <form class="form record-form" method="post" enctype="multipart/form-data">
                <?= $this->select('import_type', 'Record type', (string) ($_POST['import_type'] ?? 'people'), ['people', 'plots', 'interments']) ?>
                <label>CSV file <input name="csv_file" type="file" accept=".csv,text/csv" required></label>
                <div class="actions full">
                    <button class="button" type="submit">Import CSV</button>
                    <a class="button secondary" href="/exports">Download exports</a>
                </div>
            </form>
        </section>
        <section class="card public-section">
            <h2>CSV Headers</h2>
            <p class="lede">Use these headers, or download an export and edit it. Extra columns are ignored unless they match a configured custom field import key or label.</p>
            <div class="template-list">
                <?php foreach ($templates as $label => $headers): ?>
                    <div>
                        <p class="card-label"><?= e(pretty($label)) ?></p>
                        <code><?= e($headers) ?></code>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
        <?php
        return (string) ob_get_clean();
    }

    private function customFields(): string
    {
        $message = '';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $message = $this->repository->saveCustomFieldDefinition($_POST)
                ? 'Custom field created.'
                : 'Could not create the custom field. Check the label and key.';
        }

        $fields = $this->repository->customFieldDefinitions();

        ob_start();
        ?>
        <section class="panel">
            <p class="eyebrow">Custom fields</p>
            <h1>Custom Fields</h1>
            <p class="lede">Add cemetery-specific fields for records that do not fit the standard forms, such as old map numbers, deed book references, or local plot categories.</p>
            <?php if ($message): ?><div class="notice"><?= e($message) ?></div><?php endif; ?>
            <form class="form record-form" method="post">
                <?= $this->select('entity_type', 'Record type', (string) ($_POST['entity_type'] ?? 'plot'), ['plot', 'person', 'interment']) ?>
                <label>Label <input name="label" value="<?= e($_POST['label'] ?? '') ?>" placeholder="Lot, Map page, Old register number" required></label>
                <label>Import key <input name="field_key" value="<?= e($_POST['field_key'] ?? '') ?>" placeholder="lot, map_page, old_register_number"></label>
                <?= $this->select('field_type', 'Field type', (string) ($_POST['field_type'] ?? 'text'), ['text', 'textarea', 'date', 'number', 'url']) ?>
                <label>Sort order <input name="sort_order" type="number" value="<?= e($_POST['sort_order'] ?? '0') ?>"></label>
                <label class="full">Help text <input name="help_text" value="<?= e($_POST['help_text'] ?? '') ?>" placeholder="Optional note shown under the field"></label>
                <label class="checkbox full"><input name="is_required" type="checkbox" value="1"<?= isset($_POST['is_required']) ? ' checked' : '' ?>> Required field</label>
                <div class="actions full"><button class="button" type="submit">Add custom field</button></div>
            </form>
        </section>
        <section class="card public-section">
            <h2>Configured Fields</h2>
            <?php if (!$fields): ?>
                <p class="lede">No custom fields are configured yet.</p>
            <?php else: ?>
                <table class="table" style="margin-top:14px">
                    <thead><tr><th>Record</th><th>Label</th><th>Import key</th><th>Type</th><th>Required</th></tr></thead>
                    <tbody>
                    <?php foreach ($fields as $field): ?>
                        <tr>
                            <td><?= e(pretty($field['entity_type'])) ?></td>
                            <td><strong><?= e($field['label']) ?></strong></td>
                            <td><code><?= e($field['field_key']) ?></code></td>
                            <td><?= e(pretty($field['field_type'])) ?></td>
                            <td><?= ((int) $field['is_required']) === 1 ? 'Yes' : 'No' ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </section>
        <?php
        return (string) ob_get_clean();
    }

    private function downloadCsv(string $file): void
    {
        $type = str_ends_with($file, '.csv') ? substr($file, 0, -4) : $file;
        $export = $this->repository->export($type);
        if ($export === null) {
            http_response_code(404);
            echo 'Export not found';
            return;
        }

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="anesti-' . $type . '-' . date('Y-m-d') . '.csv"');

        $output = fopen('php://output', 'w');
        if ($output === false) {
            return;
        }

        fputcsv($output, $export['headers']);
        foreach ($export['rows'] as $row) {
            fputcsv($output, array_map(fn (string $header) => $row[$header] ?? '', $export['headers']));
        }
        fclose($output);
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
                <?= $this->customFieldInputs('person', $id) ?>
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
                <?= $this->customFieldInputs('plot', $id) ?>
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

    private function intermentForm(?string $id): string
    {
        $interment = $id === null ? [] : $this->repository->interment($id);
        if ($id !== null && !$interment) {
            http_response_code(404);
            return '<section class="panel"><h1>Interment not found</h1><p class="lede">That interment record could not be found.</p></section>';
        }

        $message = '';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $savedId = $this->repository->saveInterment($id, $_POST, $_FILES, $this->root);
            if ($savedId !== null) {
                header('Location: /interments/' . rawurlencode($savedId) . '/edit?saved=1');
                return '';
            }
            $message = 'Could not save the interment. Choose a person and plot, then try again.';
            $interment = $_POST;
        } elseif (($_GET['saved'] ?? '') === '1') {
            $message = 'Interment saved.';
        }

        $photos = $id === null ? [] : $this->repository->mediaForInterment($id);

        ob_start();
        ?>
        <section class="panel">
            <p class="eyebrow">Interments</p>
            <h1><?= $id === null ? 'Add interment' : 'Edit interment' ?></h1>
            <p class="lede">This is the link between a person and a plot. Add a second interment using the same plot for a double burial or cremains.</p>
            <?php if ($message): ?><div class="notice"><?= e($message) ?></div><?php endif; ?>
            <form class="form record-form" method="post" enctype="multipart/form-data">
                <label>Person
                    <select name="person_id" required>
                        <option value="">Choose a person</option>
                        <?php foreach ($this->repository->personOptions() as $person): ?>
                            <option value="<?= e($person['id']) ?>"<?= ($interment['person_id'] ?? '') === $person['id'] ? ' selected' : '' ?>><?= e($person['legal_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Plot
                    <select name="plot_id" required>
                        <option value="">Choose a plot</option>
                        <?php foreach ($this->repository->plotOptions() as $plot): ?>
                            <option value="<?= e($plot['id']) ?>"<?= ($interment['plot_id'] ?? '') === $plot['id'] ? ' selected' : '' ?>><?= e($plot['identifier']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <?= $this->select('disposition_type', 'Casket or cremains', $interment['disposition_type'] ?? 'unknown', ['unknown', 'casket', 'cremains', 'other']) ?>
                <label>Interment date text <input name="interment_date_text" value="<?= e($interment['interment_date_text'] ?? '') ?>" placeholder="June 1946 or unknown"></label>
                <label>Burial permit number <input name="burial_permit_number" value="<?= e($interment['burial_permit_number'] ?? '') ?>"></label>
                <label>Position in plot <input name="plot_position" value="<?= e($interment['plot_position'] ?? '') ?>" placeholder="North side, spouse side, urn area"></label>
                <?= $this->select('confidence', 'Confidence', $interment['confidence'] ?? 'unknown', ['confirmed', 'probable', 'conflicting', 'unknown']) ?>
                <?= $this->select('visibility', 'Visibility', $interment['visibility'] ?? 'private', ['private', 'public']) ?>
                <?= $this->customFieldInputs('interment', $id) ?>
                <label class="full">Marker transcription <textarea name="marker_transcription" rows="4"><?= e($interment['marker_transcription'] ?? '') ?></textarea></label>
                <label class="full">Notes <textarea name="notes" rows="4"><?= e($interment['notes'] ?? '') ?></textarea></label>
                <label class="full">Add grave photo URL <input name="photo_url" placeholder="https://example.org/grave-photo.jpg"></label>
                <label class="full">Upload grave photo <input name="photo_upload" type="file" accept="image/jpeg,image/png,image/gif,image/webp"></label>
                <?php if ($photos): ?>
                    <div class="full photo-list">
                        <p class="card-label">Attached photos</p>
                        <?php foreach ($photos as $photo): ?>
                            <a href="<?= e($photo['url']) ?>" target="_blank" rel="noreferrer"><?= e($photo['title']) ?></a>
                            <?php if (str_starts_with((string) $photo['url'], '/uploads/')): ?>
                                <img src="<?= e($photo['url']) ?>" alt="<?= e($photo['title']) ?>">
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <div class="actions full">
                    <button class="button" type="submit">Save interment</button>
                    <a class="button secondary" href="/interments">Back to interments</a>
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

    private function customFieldInputs(string $entityType, ?string $entityId): string
    {
        $definitions = $this->repository->customFieldDefinitions($entityType);
        if (!$definitions) {
            return '';
        }

        $values = $_SERVER['REQUEST_METHOD'] === 'POST'
            ? (array) ($_POST['custom'] ?? [])
            : ($entityId === null ? [] : $this->repository->customFieldValues($entityType, $entityId));

        $html = '<div class="full custom-field-group"><p class="card-label">Custom fields</p></div>';
        foreach ($definitions as $definition) {
            $key = (string) $definition['field_key'];
            $label = e($definition['label']);
            $value = e($values[$key] ?? '');
            $name = 'custom[' . e($key) . ']';
            $required = ((int) $definition['is_required']) === 1 ? ' required' : '';
            $help = $definition['help_text'] ? '<span class="field-help">' . e($definition['help_text']) . '</span>' : '';

            if ($definition['field_type'] === 'textarea') {
                $input = '<textarea name="' . $name . '" rows="3"' . $required . '>' . $value . '</textarea>';
            } else {
                $type = in_array($definition['field_type'], ['date', 'number', 'url'], true) ? $definition['field_type'] : 'text';
                $input = '<input name="' . $name . '" type="' . e($type) . '" value="' . $value . '"' . $required . '>';
            }

            $class = $definition['field_type'] === 'textarea' ? ' class="full"' : '';
            $html .= '<label' . $class . '>' . $label . $input . $help . '</label>';
        }

        return $html;
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

    private function intermentsTable(array $interments): string
    {
        $html = '<table class="table" style="margin-top:14px"><thead><tr><th>Person</th><th>Plot</th><th>Type</th><th>Date</th><th>Confidence</th><th>Visibility</th><th></th></tr></thead><tbody>';
        foreach ($interments as $interment) {
            $html .= '<tr><td><strong>' . e($interment['person_name'] ?? 'Unknown') . '</strong></td><td>' . e($interment['plot_identifier'] ?? 'Unknown') . '</td><td>' . e(pretty($interment['disposition_type'] ?? 'unknown')) . '</td><td>' . e($interment['interment_date_text'] ?: 'Unknown') . '</td><td>' . e(pretty($interment['confidence'])) . '</td><td>' . e(pretty($interment['visibility'])) . '</td><td><a class="table-action" href="/interments/' . e($interment['id']) . '/edit">Edit</a></td></tr>';
        }
        return $html . '</tbody></table>';
    }

    private function publicIntermentsTable(array $interments, string $query = ''): string
    {
        if (!$interments) {
            return '<p class="lede">' . ($query === '' ? 'No public interments are available yet.' : 'No public interments match this search.') . '</p>';
        }

        $html = '<table class="table" style="margin-top:14px"><thead><tr><th>Name</th><th>Plot</th><th>Dates</th><th>Photo</th></tr></thead><tbody>';
        foreach ($interments as $interment) {
            $dates = trim(($interment['birth_date_text'] ?? '') . ' - ' . ($interment['death_date_text'] ?? ''), ' -');
            $photo = !empty($interment['photo_url']) ? '<a class="table-action" href="' . e($interment['photo_url']) . '" target="_blank" rel="noreferrer">Photo</a>' : '';
            $html .= '<tr><td><strong>' . e($interment['person_name']) . '</strong></td><td>' . e($interment['plot_identifier']) . '</td><td>' . e($dates ?: 'Unknown') . '</td><td>' . $photo . '</td></tr>';
        }
        return $html . '</tbody></table>';
    }

    private function layout(string $title, string $route, string $content): void
    {
        $nav = ['dashboard' => 'Dashboard', 'people' => 'People', 'plots' => 'Plots', 'interments' => 'Interments', 'search' => 'Search', 'map' => 'Map', 'public' => 'Public Page', 'imports' => 'Imports', 'custom-fields' => 'Custom Fields', 'exports' => 'Exports', 'tutorial' => 'Tutorial', 'install' => 'Install'];
        ?>
        <!doctype html>
        <html lang="en">
        <head>
            <meta charset="utf-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title><?= e($title) ?> - Anesti</title>
            <link rel="stylesheet" href="/assets/app.css">
        </head>
        <body class="route-<?= e($route) ?>">
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
