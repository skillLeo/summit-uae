<?php

namespace Tests\Feature\System;

use App\Health\MySqlDump;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * The backups' dumper: MySQL's mysqldump gets --set-gtid-purged=OFF; MariaDB's mysqldump (the Hostinger server) has
 * no such option and stopped every backup with "unknown variable", so it is left out there.
 */
class MySqlDumpTest extends TestCase
{
    private string $folder;

    protected function tearDown(): void
    {
        File::deleteDirectory($this->folder);
        parent::tearDown();
    }

    public function test_mysql_dump_binary_gets_gtid_off(): void
    {
        $command = $this->commandWith('mysqldump  Ver 9.6.0 for macos15 on arm64 (Homebrew)');

        $this->assertStringContainsString('--set-gtid-purged=OFF', $command);
    }

    public function test_mariadb_dump_binary_gets_no_gtid_option(): void
    {
        $command = $this->commandWith('mysqldump from 11.8.9-MariaDB, client 10.20 for Linux (x86_64)');

        $this->assertStringNotContainsString('gtid', $command);
        $this->assertStringContainsString('--single-transaction', $command);
    }

    private function commandWith(string $version): string
    {
        $this->folder = storage_path('framework/testing/mysqldump-'.bin2hex(random_bytes(4)));
        File::ensureDirectoryExists($this->folder);
        File::put($this->folder.'/mysqldump', "#!/bin/sh\necho '{$version}'\n");
        chmod($this->folder.'/mysqldump', 0755);

        return (new MySqlDump)
            ->setDbName('summit')
            ->useSingleTransaction()
            ->skipLockTables()
            ->setDumpBinaryPath($this->folder)
            ->getDumpCommand('/tmp/dump.sql', '/tmp/credentials.cnf');
    }
}
