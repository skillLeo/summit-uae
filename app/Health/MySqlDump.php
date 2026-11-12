<?php

namespace App\Health;

use Spatie\DbDumper\Databases\MySql;

/**
 * The backups' MySQL dumper (App\Providers\SystemServiceProvider): --set-gtid-purged=OFF always. With "AUTO" a recent
 * mysqldump runs FLUSH TABLES WITH READ LOCK for --single-transaction, which needs the RELOAD privilege that an app's
 * database user does not have; this single, unreplicated database has no GTIDs to carry over anyway.
 *
 * MariaDB's mysqldump (the Hostinger server) has no GTID option and stops at it ("unknown variable
 * 'set-gtid-purged=OFF'"), so the option is left out when the dump binary reports MariaDB.
 */
final class MySqlDump extends MySql
{
    protected string $setGtidPurged = 'OFF';

    /** @var array<string, bool> whether the mysqldump in each folder is MariaDB's */
    private static array $mariaDb = [];

    public function setGtidPurged(string $setGtidPurged): static
    {
        $this->setGtidPurged = 'OFF';

        return $this;
    }

    public function getDumpCommand(string $dumpFile, string $temporaryCredentialsFile): string
    {
        $this->setGtidPurged = self::isMariaDb($this->dumpBinaryPath) ? 'AUTO' : 'OFF';

        return parent::getDumpCommand($dumpFile, $temporaryCredentialsFile);
    }

    /** Whether `mysqldump --version` in this folder ('' = on the PATH) names MariaDB. */
    public static function isMariaDb(string $folder): bool
    {
        if (! array_key_exists($folder, self::$mariaDb)) {
            $output = @shell_exec(escapeshellarg($folder.'mysqldump').' --version 2>&1');
            self::$mariaDb[$folder] = is_string($output) && stripos($output, 'mariadb') !== false;
        }

        return self::$mariaDb[$folder];
    }
}
