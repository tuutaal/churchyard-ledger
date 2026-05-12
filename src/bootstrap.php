<?php
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

spl_autoload_register(function (string $class): void {
    $prefix = 'Anesti\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $path = __DIR__ . '/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($path)) {
        require_once $path;
    }
});

require_once __DIR__ . '/helpers.php';
