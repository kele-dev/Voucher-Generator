<?php

namespace App\Console\Commands;

use App\Enums\BatchStatus;
use App\Models\GenerationBatch;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class PruneExportsCommand extends Command
{
    protected $signature = 'voucher:prune-exports';

    protected $description = 'Menghapus ZIP export yang kedaluwarsa dan file ekspor yatim.';

    public function handle(): int
    {
        $disk = Storage::disk('local');
        $cutoff = now()->subDays(max(0, (int) config('voucher.export_retention_days', 30)));

        $referenced = GenerationBatch::query()
            ->whereNotNull('export_path')
            ->pluck('export_path')
            ->all();

        $pruned = 0;

        GenerationBatch::query()
            ->where('export_status', BatchStatus::Completed)
            ->whereNotNull('export_path')
            ->whereNotNull('exported_at')
            ->where('exported_at', '<', $cutoff)
            ->get()
            ->each(function (GenerationBatch $batch) use ($disk, &$pruned): void {
                if ($disk->exists($batch->export_path)) {
                    $disk->delete($batch->export_path);
                }

                // Leave the batch so it can be exported again, but forget the
                // file that is now gone.
                $batch->forceFill([
                    'export_path' => null,
                    'export_status' => null,
                    'exported_at' => null,
                ])->save();

                $pruned++;
            });

        $orphanCutoff = now()->subDay()->getTimestamp();
        $orphans = 0;

        foreach ($disk->files('exports') as $file) {
            if (in_array($file, $referenced, true)) {
                continue;
            }

            if ($disk->lastModified($file) < $orphanCutoff) {
                $disk->delete($file);
                $orphans++;
            }
        }

        $this->info("Export lama dihapus: {$pruned}, file yatim dihapus: {$orphans}.");

        return self::SUCCESS;
    }
}
