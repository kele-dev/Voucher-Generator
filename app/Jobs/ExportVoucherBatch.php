<?php

namespace App\Jobs;

use App\Enums\BatchStatus;
use App\Models\GenerationBatch;
use App\Services\Voucher\VoucherRenderer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;
use ZipArchive;

/**
 * Renders every voucher of a completed batch into a ZIP. Guarded by a claim so
 * a duplicate (or a resurrected, dead) job cannot corrupt the result.
 */
class ExportVoucherBatch implements ShouldQueue
{
    use Queueable;

    public int $timeout = 1800;

    public function __construct(
        public int $batchId,
        public ?string $claim = null,
    ) {
    }

    public function handle(VoucherRenderer $renderer): void
    {
        $batch = GenerationBatch::find($this->batchId);

        if ($batch === null || $batch->export_status === BatchStatus::Completed) {
            return;
        }

        $claim = $this->claim ?? (string) Str::uuid();

        // Never steal an export another worker is actively running.
        if ($this->isLive($batch) && $batch->export_claim !== $claim) {
            return;
        }

        $batch->forceFill([
            'export_status' => BatchStatus::Processing,
            'export_claim' => $claim,
            'export_error' => null,
            'exported_count' => 0,
        ])->save();

        try {
            $this->export($batch, $renderer, $claim);
        } catch (Throwable $exception) {
            $this->markFailed($batch, $claim, $exception);

            throw $exception;
        }
    }

    public function failed(Throwable $exception): void
    {
        $batch = GenerationBatch::find($this->batchId);

        if ($batch === null) {
            return;
        }

        // Only the job that still owns the claim may record the failure.
        if ($this->claim !== null && $batch->export_claim !== $this->claim) {
            return;
        }

        $this->markFailed($batch, $this->claim, $exception);
    }

    private function export(GenerationBatch $batch, VoucherRenderer $renderer, string $claim): void
    {
        $disk = Storage::disk('local');
        $format = $batch->export_format ?: 'jpg';
        $scale = (int) ($batch->export_scale ?: 100);

        $vouchers = $batch->vouchers()->orderBy('id')->get();

        File::ensureDirectoryExists($disk->path('exports'));

        $relative = 'exports/batch-'.$batch->id.'-'.Str::lower(Str::random(8)).'.'.$format;

        $zip = new ZipArchive;
        $zip->open($disk->path($relative), ZipArchive::CREATE | ZipArchive::OVERWRITE);

        $count = 0;

        foreach ($vouchers as $voucher) {
            $data = $renderer->encode($voucher, $format, null, $scale);
            $zip->addFromString($voucher->code.'.'.$format, $data);
            $count++;
        }

        $zip->close();

        $batch->forceFill([
            'export_path' => $relative,
            'export_status' => BatchStatus::Completed,
            'export_claim' => null,
            'export_format' => $format,
            'export_scale' => $scale,
            'exported_count' => $count,
            'exported_at' => now(),
            'export_error' => null,
        ])->save();

        unset($claim);
    }

    private function markFailed(GenerationBatch $batch, ?string $claim, Throwable $exception): void
    {
        $batch->forceFill([
            'export_status' => BatchStatus::Failed,
            'export_claim' => null,
            'export_error' => $exception->getMessage(),
        ])->save();
    }

    private function isLive(GenerationBatch $batch): bool
    {
        return $batch->export_status === BatchStatus::Processing
            && ! $batch->exportIsStale();
    }
}
