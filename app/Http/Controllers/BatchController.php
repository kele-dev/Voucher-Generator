<?php

namespace App\Http\Controllers;

use App\Enums\BatchStatus;
use App\Jobs\ExportVoucherBatch;
use App\Jobs\GenerateVoucherBatch;
use App\Models\Campaign;
use App\Models\GenerationBatch;
use App\Services\Voucher\CodePattern;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class BatchController extends Controller
{
    public function store(Request $request, Campaign $campaign)
    {
        $this->authorize('generate', $campaign);

        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:'.(int) config('voucher.batches.max_count')],
            'idempotency_key' => ['required', 'string', 'max:100'],
            'export_format' => ['nullable', 'string', Rule::in(config('voucher.exports.formats'))],
            'export_scale' => ['nullable', 'integer', Rule::in(config('voucher.exports.scales'))],
        ]);

        // A retried submit must not create a second batch.
        $existing = GenerationBatch::query()
            ->where('idempotency_key', $data['idempotency_key'])
            ->first();

        if ($existing !== null) {
            if ((int) $existing->campaign_id !== (int) $campaign->id) {
                return response()->json(['message' => 'Idempotency key sudah dipakai campaign lain.'], 409);
            }

            return response()->json(['id' => $existing->id, 'status' => $existing->status?->value], 200);
        }

        if (! $campaign->canGenerate()) {
            throw ValidationException::withMessages([
                'quantity' => 'Campaign tidak aktif atau sudah berakhir.',
            ]);
        }

        $quantity = (int) $data['quantity'];
        $errors = [];

        if ($campaign->generation_limit !== null
            && $campaign->committedCount() + $quantity > $campaign->generation_limit) {
            $errors['quantity'] = 'Melebihi batas total voucher campaign.';
        }

        $pattern = CodePattern::parse((string) $campaign->code_pattern);

        if ($pattern->hasSequence()) {
            $skeleton = $pattern->skeleton((string) $campaign->code_prefix, CarbonImmutable::now());
            $last = (int) DB::table('code_sequences')->where('key', $skeleton)->value('last_value');
            $available = $pattern->capacity() - $last;

            if ($quantity > $available) {
                $errors['quantity'] = "Rentang kode hampir habis: sisa {$available} kode untuk pola ini.";
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $batch = $campaign->batches()->create([
            'created_by' => $request->user()->id,
            'idempotency_key' => $data['idempotency_key'],
            'requested_count' => $quantity,
            'status' => BatchStatus::Pending,
            'export_format' => $data['export_format'] ?? 'jpg',
            'export_scale' => $data['export_scale'] ?? 100,
        ]);

        GenerateVoucherBatch::dispatch($batch->id);

        return response()->json(['id' => $batch->id, 'status' => $batch->fresh()->status?->value], 202);
    }

    public function show(GenerationBatch $batch)
    {
        $this->authorize('view', $batch);

        return response()->json($batch->toStatusArray());
    }

    public function export(Request $request, GenerationBatch $batch)
    {
        $this->authorize('export', $batch);

        $data = $request->validate([
            'export_format' => ['nullable', 'string', Rule::in(config('voucher.exports.formats'))],
            'export_scale' => ['nullable', 'integer', Rule::in(config('voucher.exports.scales'))],
        ]);

        if ($batch->export_status === BatchStatus::Processing && ! $batch->exportIsStale()) {
            throw ValidationException::withMessages(['export' => 'Export sedang berjalan.']);
        }

        $old = $batch->export_path;

        $batch->forceFill([
            'export_status' => BatchStatus::Pending,
            'export_format' => $data['export_format'] ?? $batch->export_format ?? 'jpg',
            'export_scale' => $data['export_scale'] ?? $batch->export_scale ?? 100,
            'export_error' => null,
        ])->save();

        ExportVoucherBatch::dispatch($batch->id);

        $batch->refresh();

        if (filled($old) && $old !== $batch->export_path) {
            Storage::disk('local')->delete($old);
        }

        return response()->json(['id' => $batch->id, 'export_status' => $batch->export_status?->value], 202);
    }

    public function retry(GenerationBatch $batch)
    {
        $this->authorize('export', $batch);

        if ($batch->export_status === BatchStatus::Completed) {
            throw ValidationException::withMessages(['batch' => 'Batch sudah selesai diproses.']);
        }

        if ($batch->export_status === BatchStatus::Processing && ! $batch->exportIsStale()) {
            throw ValidationException::withMessages(['batch' => 'Export sedang berjalan.']);
        }

        if ($batch->status === BatchStatus::Completed) {
            // Codes exist already: only the export needs to be redone.
            $batch->forceFill(['export_status' => BatchStatus::Pending, 'export_error' => null])->save();

            ExportVoucherBatch::dispatch($batch->id);
        } else {
            GenerateVoucherBatch::dispatch($batch->id);
        }

        return response()->json(['id' => $batch->id], 202);
    }

    public function download(GenerationBatch $batch)
    {
        $this->authorize('download', $batch);

        abort_if(blank($batch->export_path), 404);

        return Storage::disk('local')->download($batch->export_path, $batch->exportFilename());
    }
}
