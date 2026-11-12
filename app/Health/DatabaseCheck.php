<?php

namespace App\Health;

use Illuminate\Support\Facades\DB;
use Spatie\Health\Checks\Check;
use Spatie\Health\Checks\Result;
use Throwable;

/** Database: the default connection answers a query. */
final class DatabaseCheck extends Check
{
    protected ?string $name = 'database';

    public function run(): Result
    {
        try {
            $connection = DB::connection();
            $connection->select('select 1');
            $version = (string) rescue(fn () => $connection->getServerVersion(), '', report: false);

            $driver = match ($connection->getDriverName()) {
                'mysql' => 'MySQL',
                'mariadb' => 'MariaDB',
                'pgsql' => 'PostgreSQL',
                'sqlite' => 'SQLite',
                'sqlsrv' => 'SQL Server',
                default => $connection->getDriverName(),
            };

            return Outcome::ok('database_ok', ['driver' => trim($driver.' '.$version)]);
        } catch (Throwable) {
            // the exception may carry the host or the user name: not shown
            return Outcome::failed('database_failed');
        }
    }
}
