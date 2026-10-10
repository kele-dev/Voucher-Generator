<?php

namespace App\Console\Commands;

use App\Models\GenerationBatch;
use App\Services\Backup\BackupManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

class RestoreCommand extends Command
{
    protected $signature = 'voucher:restore
        {backup : Nama folder backup}
        {--force : Lewati konfirmasi}
        {--wait=0 : Detik menunggu job queue selesai sebelum dibatalkan}';

    protected $description = 'Mengembalikan database dan storage dari sebuah backup.';

    public function handle(BackupManager $backups): int
    {
        $dir = $backups->root().'/'.$this->argument('backup');

        if (! is_dir($dir)) {
            $this->error('Backup tidak ditemukan.');

            return self::FAILURE;
        }

        $problems = $backups->verify($dir);

        if ($problems !== []) {
            foreach ($problems as $problem) {
                $this->error($problem);
            }

            $this->error('Backup tidak valid, restore dibatalkan.');

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm('Restore sekarang? Data saat ini akan diganti.')) {
            $this->warn('Restore dibatalkan.');

            return self::FAILURE;
        }

        if (! $this->waitForJobs((int) $this->option('wait'))) {
            $this->error('Masih ada job queue yang berjalan. Hentikan worker lalu ulangi.');

            return self::FAILURE;
        }

        $this->call('down');

        try {
            // Keep the current state, in case the backup is not what we want.
            $backups->create();

            $this->restoreDatabase($dir);
            $this->restoreStorage($dir);
            $this->clearMissingExportLinks();

            $this->call('migrate', ['--force' => true]);
            $this->call('queue:restart');
        } finally {
            $this->call('up');
        }

        $this->info('Restore selesai.');

        return self::SUCCESS;
    }

    private function waitForJobs(int $seconds): bool
    {
        $deadline = now()->addSeconds(max(0, $seconds));

        while ($this->jobsRunning()) {
            if (now()->greaterThanOrEqualTo($deadline)) {
                return false;
            }

            sleep(1);
        }

        return true;
    }

    private function jobsRunning(): bool
    {
        return DB::table('jobs')->whereNotNull('reserved_at')->exists();
    }

    private function restoreDatabase(string $dir): void
    {
        $target = (string) config('database.connections.sqlite.database');

        DB::disconnect('sqlite');

        foreach ([$target.'-wal', $target.'-shm'] as $sidecar) {
            if (is_file($sidecar)) {
                @unlink($sidecar);
            }
        }

        File::copy($dir.'/database.sqlite', $target);

        DB::purge('sqlite');
        DB::reconnect('sqlite');
    }

    private function restoreStorage(string $dir): void
    {
        $disk = Storage::disk('local');

        foreach ($disk->allFiles() as $file) {
            $disk->delete($file);
        }

        foreach ($disk->allDirectories() as $directory) {
            $disk->deleteDirectory($directory);
        }

        if (! is_file($dir.'/storage.zip')) {
            return;
        }

        $zip = new ZipArchive;
        $zip->open($dir.'/storage.zip');
        $zip->extractTo($disk->path(''));
        $zip->close();
    }

    private function clearMissingExportLinks(): void
    {
        $disk = Storage::disk('local');

        GenerationBatch::query()->whereNotNull('export_path')->get()
            ->each(function (GenerationBatch $batch) use ($disk): void {
                if (! $disk->exists($batch->export_path)) {
                    $batch->forceFill([
                        'export_path' => null,
                        'export_status' => null,
                        'exported_at' => null,
                    ])->save();
                }
            });
    }
}
