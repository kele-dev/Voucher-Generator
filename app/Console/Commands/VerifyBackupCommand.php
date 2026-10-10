<?php

namespace App\Console\Commands;

use App\Services\Backup\BackupManager;
use Illuminate\Console\Command;

class VerifyBackupCommand extends Command
{
    protected $signature = 'voucher:backup:verify {backup? : Nama folder backup, default terbaru}';

    protected $description = 'Memeriksa keutuhan sebuah backup (hash + integritas database).';

    public function handle(BackupManager $backups): int
    {
        $name = $this->argument('backup');

        if ($name !== null) {
            $dir = $backups->root().'/'.$name;
        } else {
            $latest = $backups->latest();
            $dir = $latest['path'] ?? null;
        }

        if ($dir === null || ! is_dir($dir)) {
            $this->error('Backup tidak ditemukan.');

            return self::FAILURE;
        }

        $problems = $backups->verify($dir);

        if ($problems !== []) {
            foreach ($problems as $problem) {
                $this->error($problem);
            }

            $this->error('Backup tidak valid: '.basename($dir));

            return self::FAILURE;
        }

        $this->info('Backup valid: '.basename($dir));

        return self::SUCCESS;
    }
}
