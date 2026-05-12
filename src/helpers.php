<?php
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Anesti;

function e(string|int|null $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function pretty(?string $value): string
{
    if ($value === null || $value === '') {
        return 'Unknown';
    }

    return ucwords(str_replace('_', ' ', $value));
}

function active(string $route, string $current): string
{
    return $route === $current ? ' class="active"' : '';
}
