<?php
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

require_once __DIR__ . '/src/bootstrap.php';

$app = new Anesti\App(__DIR__);
$app->handle($_SERVER['REQUEST_URI'] ?? '/');
