<?php

namespace App\Console\Commands;

use App\Services\Backup\BackupManager;
use Illuminate\Console\Command;

class BackupCommand extends Command
{
    protected $signature = 'voucher:backup';

    protected $description = 'Membuat backup database dan storage privat, lalu memangkas yang lama.';

    public function handle(BackupManager $backups): int
    {
        $dir = $backups->create();

        $this->info('Backup dibuat: '.basename($dir));

        $backups->prune();

        return self::SUCCESS;
    }
}
