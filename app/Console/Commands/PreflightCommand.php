<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class PreflightCommand extends Command
{
    protected $signature = 'voucher:preflight';

    protected $description = 'Memeriksa konfigurasi sebelum dipakai di produksi.';

    public function handle(): int
    {
        $problems = [];

        if (app()->environment() !== 'production') {
            $problems[] = 'APP_ENV belum "production".';
        }

        if (config('app.debug')) {
            $problems[] = 'APP_DEBUG harus false di produksi.';
        }

        if (! str_starts_with((string) config('app.url'), 'https://')) {
            $problems[] = 'APP_URL harus memakai https.';
        }

        if (! config('session.secure')) {
            $problems[] = 'SESSION_SECURE_COOKIE harus aktif.';
        }

        if (! config('session.http_only')) {
            $problems[] = 'SESSION_HTTP_ONLY harus aktif.';
        }

        $logLevel = config('logging.channels.single.level') ?? config('logging.channels.daily.level');

        if (! in_array($logLevel, ['emergency', 'alert', 'critical', 'error', 'warning'], true)) {
            $problems[] = 'Level log terlalu detail untuk produksi (pakai warning atau lebih ketat).';
        }

        if (config('queue.default') !== 'database') {
            $problems[] = 'Queue harus memakai koneksi "database".';
        }

        if (config('database.default') === 'sqlite' && config('cache.default') === 'database') {
            $problems[] = 'Cache store tidak di database SQLite: sinyal queue:restart akan ikut tertimpa saat restore.';
        }

        $backupPath = (string) config('voucher.backups.path');

        File::ensureDirectoryExists($backupPath);

        if (! is_dir($backupPath) || ! is_writable($backupPath)) {
            $problems[] = 'Folder backup tidak bisa ditulis: '.$backupPath;
        }

        foreach ($problems as $problem) {
            $this->error($problem);
        }

        if ($problems !== []) {
            $this->error('Belum siap produksi.');

            return self::FAILURE;
        }

        $this->info('Siap produksi.');

        return self::SUCCESS;
    }
}
