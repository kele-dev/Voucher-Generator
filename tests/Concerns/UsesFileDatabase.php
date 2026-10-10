<?php

namespace Tests\Concerns;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * Runs a test against a real SQLite file (WAL, like production) instead of
 * the shared in-memory database. Needed for anything that copies, snapshots
 * or reopens the database file.
 */
trait UsesFileDatabase
{
    protected string $databaseFile;

    protected function setUpFileDatabase(): void
    {
        $this->databaseFile = storage_path('framework/testing/db-'.uniqid().'.sqlite');
        @mkdir(dirname($this->databaseFile), 0777, true);
        touch($this->databaseFile);

        config(['database.connections.sqlite.database' => $this->databaseFile]);
        DB::purge('sqlite');
        Artisan::call('migrate', ['--force' => true]);
    }

    protected function tearDownFileDatabase(): void
    {
        DB::disconnect('sqlite');
        foreach ([$this->databaseFile, $this->databaseFile.'-wal', $this->databaseFile.'-shm'] as $file) {
            @unlink($file);
        }
    }
}
