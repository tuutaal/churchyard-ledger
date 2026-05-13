<?php
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Anesti;

final class Env
{
    public function __construct(private readonly string $root)
    {
    }

    public function all(): array
    {
        $values = [];
        $path = $this->root . '/.env';
        if (is_file($path)) {
            foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
                $line = trim($line);
                if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                    continue;
                }

                [$key, $value] = explode('=', $line, 2);
                $values[trim($key)] = trim(trim($value), "\"'");
            }
        }

        foreach (self::keys() as $key) {
            $value = getenv($key);
            if ($value !== false) {
                $values[$key] = $value;
            }
        }

        return $values;
    }

    public function write(array $values): bool
    {
        $lines = [
            'ANESTI_DB_HOST=' . $values['ANESTI_DB_HOST'],
            'ANESTI_DB_PORT=' . $values['ANESTI_DB_PORT'],
            'ANESTI_DB_NAME=' . $values['ANESTI_DB_NAME'],
            'ANESTI_DB_USER=' . $values['ANESTI_DB_USER'],
            'ANESTI_DB_PASSWORD=' . $values['ANESTI_DB_PASSWORD'],
            'ANESTI_APP_URL=' . $values['ANESTI_APP_URL'],
            'ANESTI_ENV=production',
        ];

        return file_put_contents($this->root . '/.env', implode(PHP_EOL, $lines) . PHP_EOL) !== false;
    }

    private static function keys(): array
    {
        return [
            'DATABASE_URL',
            'ANESTI_DB_HOST',
            'ANESTI_DB_PORT',
            'ANESTI_DB_NAME',
            'ANESTI_DB_USER',
            'ANESTI_DB_PASSWORD',
            'ANESTI_APP_URL',
            'ANESTI_ENV',
        ];
    }
}
