<?php

namespace App\Jobs;

use App\Enums\BatchStatus;
use App\Models\Campaign;
use App\Models\GenerationBatch;
use App\Models\Voucher;
use App\Services\Voucher\CodePattern;
use App\Services\Voucher\VoucherCodeGenerator;
use App\Services\Voucher\VoucherContent;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Issues the voucher codes for a batch. Safe to run more than once: it resumes
 * from the stored generated_count and only ever reaches requested_count.
 */
class GenerateVoucherBatch implements ShouldQueue
{
    use Queueable;

    public int $timeout = 1800;

    public function __construct(public int $batchId)
    {
    }

    public function handle(VoucherCodeGenerator $generator): void
    {
        $batch = GenerationBatch::find($this->batchId);

        if ($batch === null || $batch->status === BatchStatus::Completed) {
            return;
        }

        $campaign = $batch->campaign()->with('brand', 'template', 'outlets')->firstOrFail();

        $batch->forceFill([
            'status' => BatchStatus::Processing,
            'started_at' => $batch->started_at ?? now(),
            'error' => null,
        ])->save();

        $remaining = max(0, $batch->requested_count - $batch->generated_count);

        if ($remaining > 0) {
            $pattern = CodePattern::parse((string) $campaign->code_pattern);
            $codes = $generator->generate(
                $pattern,
                (string) $campaign->code_prefix,
                CarbonImmutable::now(),
                $remaining,
            );

            $attributes = $this->voucherAttributes($campaign);

            foreach ($codes as $code) {
                $batch->vouchers()->create(['code' => $code, ...$attributes]);
            }

            $batch->forceFill([
                'generated_count' => $batch->generated_count + count($codes),
            ])->save();

            $batch->refresh();
        }

        $batch->forceFill([
            'status' => BatchStatus::Completed,
            'generated_count' => min($batch->requested_count, $batch->generated_count),
            'finished_at' => now(),
        ])->save();

        ExportVoucherBatch::dispatch($batch->id);
    }

    public function failed(Throwable $exception): void
    {
        GenerationBatch::whereKey($this->batchId)->update([
            'status' => BatchStatus::Failed,
            'error' => $exception->getMessage(),
            'finished_at' => now(),
        ]);
    }

    /**
     * The issue-time snapshot copied onto every voucher.
     *
     * @return array<string, mixed>
     */
    private function voucherAttributes(Campaign $campaign): array
    {
        $validUntil = $campaign->expires_at ?? now()->addWeeks(2);

        return [
            'campaign_id' => $campaign->id,
            'status' => 'issued',
            'voucher_template_id' => $campaign->voucher_template_id,
            'voucher_kind' => $campaign->voucher_kind,
            'voucher_type' => $campaign->voucher_type,
            'campaign_title' => $campaign->name,
            'benefit_title' => $campaign->benefit_title,
            'benefit_description' => $campaign->benefit_description,
            'terms' => $campaign->terms,
            'outlet_scope_text' => $campaign->outletScopeText(),
            'product_image_path' => $campaign->product_image_path,
            'content_snapshot' => VoucherContent::snapshot($campaign),
            'valid_from' => $campaign->starts_at ?? now(),
            'valid_until' => $validUntil,
            'issued_at' => now(),
        ];
    }
}
