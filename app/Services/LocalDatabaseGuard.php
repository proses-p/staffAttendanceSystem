<?php

namespace App\Services;

use DomainException;
use Illuminate\Support\Facades\DB;

class LocalDatabaseGuard
{
    public function assertLocal(): void
    {
        if (! app()->environment('local')) {
            throw new DomainException('This command is available only when APP_ENV=local.');
        }

        $config = DB::connection()->getConfig();
        $driver = $config['driver'] ?? null;

        if ($driver === 'sqlite') {
            return;
        }

        if (
            in_array($driver, ['mysql', 'mariadb'], true) &&
            in_array(strtolower((string) ($config['host'] ?? '')), ['localhost', '127.0.0.1', '::1'], true) &&
            empty($config['url']) &&
            empty($config['unix_socket'])
        ) {
            return;
        }

        throw new DomainException('Refusing to run: the configured database is not an explicitly local SQLite or loopback MySQL database.');
    }
}
