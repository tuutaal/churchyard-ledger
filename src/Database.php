<?php
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Anesti;

use PDO;
use PDOException;

final class Database
{
    public function __construct(private readonly array $env)
    {
    }

    public function connect(): ?PDO
    {
        $config = $this->config();
        if ($config === null) {
            return null;
        }

        try {
            return new PDO(
                sprintf(
                    'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                    $config['host'],
                    $config['port'],
                    $config['name']
                ),
                $config['user'],
                $config['password'],
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]
            );
        } catch (PDOException) {
            return null;
        }
    }

    private function config(): ?array
    {
        if (!empty($this->env['DATABASE_URL'])) {
            $parts = parse_url($this->env['DATABASE_URL']);
            if ($parts !== false && isset($parts['host'], $parts['path'], $parts['user'])) {
                return [
                    'host' => $parts['host'],
                    'port' => (string) ($parts['port'] ?? 3306),
                    'name' => ltrim($parts['path'], '/'),
                    'user' => rawurldecode($parts['user']),
                    'password' => rawurldecode((string) ($parts['pass'] ?? '')),
                ];
            }
        }

        if (empty($this->env['ANESTI_DB_NAME']) || empty($this->env['ANESTI_DB_USER'])) {
            return null;
        }

        return [
            'host' => $this->env['ANESTI_DB_HOST'] ?? 'localhost',
            'port' => $this->env['ANESTI_DB_PORT'] ?? '3306',
            'name' => $this->env['ANESTI_DB_NAME'],
            'user' => $this->env['ANESTI_DB_USER'],
            'password' => $this->env['ANESTI_DB_PASSWORD'] ?? '',
        ];
    }
}
