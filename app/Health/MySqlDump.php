<?php

namespace App\Health;

use Spatie\DbDumper\Databases\MySql;

/**
 * The backups' MySQL dumper (App\Providers\SystemServiceProvider): --set-gtid-purged=OFF always. With "AUTO" a recent
 * mysqldump runs FLUSH TABLES WITH READ LOCK for --single-transaction, which needs the RELOAD privilege that an app's
 * database user does not have; this single, unreplicated database has no GTIDs to carry over anyway.
 */
final class MySqlDump extends MySql
{
    protected string $setGtidPurged = 'OFF';

    public function setGtidPurged(string $setGtidPurged): static
    {
        $this->setGtidPurged = 'OFF';

        return $this;
    }
}
