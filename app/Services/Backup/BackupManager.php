<?php

namespace App\Services\Backup;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use PDO;
use Throwable;
use ZipArchive;

/**
 * Snapshot/restore of the SQLite database and the private storage disk.
 *
 * A backup is a folder holding a consistent database copy (SQLite VACUUM INTO,
 * which is safe while the app keeps running), an optional storage.zip and a
 * manifest.json with a sha256 per file. ZIP exports are rebuildable and left
 * out.
 */
class BackupManager
{
    public function root(): string
    {
        return (string) config('voucher.backups.path', storage_path('backups'));
    }

    /**
     * Take a backup and return the folder path.
     */
    public function create(): string
    {
        $base = $this->root().'/'.now()->format('Ymd-His');
        $dir = $base;
        $suffix = 2;

        // Two backups in the same second must not overwrite each other.
        while (File::isDirectory($dir)) {
            $dir = $base.'-'.$suffix;
            $suffix++;
        }

        File::ensureDirectoryExists($dir);

        $this->snapshotDatabase($dir.'/database.sqlite');

        $files = [
            'database.sqlite' => (string) hash_file('sha256', $dir.'/database.sqlite'),
        ];

        if ($this->zipStorage($dir.'/storage.zip')) {
            $files['storage.zip'] = (string) hash_file('sha256', $dir.'/storage.zip');
        }

        File::put($dir.'/manifest.json', json_encode([
            'created_at' => now()->toIso8601String(),
            'files' => $files,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $this->clearStatCache();

        return $dir;
    }

    /**
     * @return array{name: string, path: string, created_at: CarbonImmutable}|null
     */
    public function latest(): ?array
    {
        return $this->list()->first();
    }

    /**
     * Newest first.
     *
     * @return Collection<int, array{name: string, path: string, created_at: CarbonImmutable}>
     */
    public function list(): Collection
    {
        $root = $this->root();

        if (! File::isDirectory($root)) {
            return collect();
        }

        return collect(File::directories($root))
            ->map(function (string $dir): array {
                $manifest = $this->readManifest($dir);

                $created = isset($manifest['created_at'])
                    ? CarbonImmutable::parse((string) $manifest['created_at'])
                    : CarbonImmutable::createFromTimestamp(File::lastModified($dir));

                return [
                    'name' => basename($dir),
                    'path' => $dir,
                    'created_at' => $created,
                ];
            })
            ->sortByDesc('name')
            ->values();
    }

    /**
     * @return array<int, string> problems; empty means the backup is usable
     */
    public function verify(string $path): array
    {
        $problems = [];

        $manifestPath = $path.'/manifest.json';

        if (! is_file($manifestPath)) {
            return ['Manifest tidak ditemukan.'];
        }

        $manifest = json_decode((string) file_get_contents($manifestPath), true);
        $manifest = is_array($manifest) ? $manifest : [];

        foreach ((array) ($manifest['files'] ?? []) as $file => $hash) {
            $full = $path.'/'.$file;

            if (! is_file($full)) {
                $problems[] = "File backup hilang: {$file}.";

                continue;
            }

            if (! hash_equals((string) $hash, (string) hash_file('sha256', $full))) {
                $problems[] = "Isi file berubah (hash tidak cocok): {$file}.";
            }
        }

        if (is_file($path.'/database.sqlite')) {
            try {
                $pdo = new PDO('sqlite:'.$path.'/database.sqlite');
                $result = $pdo->query('PRAGMA integrity_check')->fetchColumn();

                if ($result !== 'ok') {
                    $problems[] = 'Database backup korup (integrity_check gagal).';
                }

                $pdo = null;
            } catch (Throwable) {
                $problems[] = 'Database backup tidak bisa dibuka.';
            }
        }

        return $problems;
    }

    /**
     * Keep the newest daily backups plus the newest of the most recent ISO
     * weeks; delete the rest.
     */
    public function prune(): void
    {
        $backups = $this->list();

        $keepDaily = max(0, (int) config('voucher.backups.keep_daily', 7));
        $keepWeekly = max(0, (int) config('voucher.backups.keep_weekly', 4));

        $keep = $backups->take($keepDaily)->pluck('name');

        $weeks = [];

        foreach ($backups as $backup) {
            $week = $backup['created_at']->format('o-W');

            if (! isset($weeks[$week])) {
                $weeks[$week] = $backup['name'];
            }

            if (count($weeks) >= $keepWeekly) {
                break;
            }
        }

        $keep = $keep->merge(array_values($weeks))->unique();

        foreach ($backups as $backup) {
            if (! $keep->contains($backup['name'])) {
                File::deleteDirectory($backup['path']);
            }
        }
    }

    private function snapshotDatabase(string $path): void
    {
        if (is_file($path)) {
            File::delete($path);
        }

        DB::statement('VACUUM INTO ?', [$path]);

        if (! is_file($path)) {
            throw new \RuntimeException('Gagal membuat salinan database.');
        }
    }

    private function zipStorage(string $zipPath): bool
    {
        $disk = Storage::disk('local');

        $files = array_values(array_filter(
            $disk->allFiles(),
            fn (string $file): bool => ! str_starts_with($file, 'exports/'),
        ));

        if ($files === []) {
            return false;
        }

        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        foreach ($files as $file) {
            $zip->addFile($disk->path($file), $file);
        }

        $zip->close();

        return is_file($zipPath);
    }

    /**
     * @return array<string, mixed>
     */
    private function readManifest(string $dir): array
    {
        $path = $dir.'/manifest.json';

        if (! is_file($path)) {
            return [];
        }

        $data = json_decode((string) file_get_contents($path), true);

        return is_array($data) ? $data : [];
    }

    private function clearStatCache(): void
    {
        clearstatcache();
    }
}
