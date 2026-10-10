@auth
    @if (auth()->user()->can('viewQueueStatus') && App\Support\QueueStatus::stalled())
        <div role="alert" class="mb-6 flex gap-3 rounded-2xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-950">
            <x-icon name="alert" class="mt-0.5 size-5 text-amber-700" />
            <div>
                <p class="font-bold">Queue worker tidak berjalan.</p>
                <p class="mt-1">Ada antrean generate/export yang belum diproses lebih dari {{ intdiv(config('voucher.queue_stall_seconds'), 60) }} menit. Voucher dan ZIP tidak akan dibuat sampai worker hidup.
                    Lokal: jalankan <code class="rounded bg-amber-100 px-1">php artisan queue:work</code>. Server: <code class="rounded bg-amber-100 px-1">sudo supervisorctl status</code>.</p>
            </div>
        </div>
    @endif
@endauth

{{-- session('status') is shown as a toast (x-toast-stack); errors stay inline. --}}

@foreach (['brand', 'template', 'campaign', 'batch', 'voucher'] as $errorKey)
    @error($errorKey)
        <div role="alert" class="mb-6 flex items-center gap-3 rounded-2xl border border-danger-line bg-danger-soft px-4 py-3 text-sm font-medium text-danger-hover">
            <x-icon name="alert" class="size-5" />
            {{ $message }}
        </div>
    @enderror
@endforeach
