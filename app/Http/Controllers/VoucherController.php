<?php

namespace App\Http\Controllers;

use App\Enums\VoucherStatus;
use App\Models\Brand;
use App\Models\Campaign;
use App\Models\Voucher;
use App\Services\Voucher\VoucherRenderer;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class VoucherController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', Voucher::class);

        $query = Voucher::query()
            ->visibleTo($request->user())
            ->with(['campaign.brand', 'template'])
            ->latest('issued_at')
            ->latest('id');

        if ($request->filled('q')) {
            $term = '%'.mb_strtoupper((string) $request->string('q')).'%';
            $query->whereRaw('upper(code) like ?', [$term]);
        }

        if ($request->filled('campaign_id')) {
            $query->where('campaign_id', $request->integer('campaign_id'));
        }

        if ($request->filled('brand_id')) {
            $query->whereHas('campaign', fn ($q) => $q->where('brand_id', $request->integer('brand_id')));
        }

        match ((string) $request->input('status')) {
            VoucherStatus::Voided->value => $query->where('status', 'voided'),
            VoucherStatus::Expired->value => $query
                ->where('status', 'issued')
                ->whereNotNull('valid_until')
                ->where('valid_until', '<', now()),
            VoucherStatus::Issued->value => $query
                ->where('status', 'issued')
                ->where(fn ($q) => $q->whereNull('valid_until')->orWhere('valid_until', '>=', now())),
            default => null,
        };

        return view('vouchers.index', [
            'vouchers' => $query->paginate(20)->withQueryString(),
            'campaigns' => Campaign::query()->orderBy('name')->get(),
            'brands' => Brand::query()->orderBy('name')->get(),
            'statuses' => VoucherStatus::cases(),
        ]);
    }

    public function show(Voucher $voucher)
    {
        $this->authorize('view', $voucher);

        return view('vouchers.show', [
            'voucher' => $voucher->load(['campaign.brand', 'template']),
        ]);
    }

    public function download(Request $request, Voucher $voucher, VoucherRenderer $renderer)
    {
        $this->authorize('download', $voucher);

        $format = strtolower((string) $request->query('format', 'jpg'));

        abort_unless(in_array($format, config('voucher.exports.formats'), true), 404);

        $image = $renderer->encode($voucher, $format);

        $disposition = $request->boolean('inline') ? 'inline' : 'attachment';

        return response($image, 200, [
            'Content-Type' => $format === 'png' ? 'image/png' : 'image/jpeg',
            'Content-Disposition' => $disposition.'; filename="'.$voucher->code.'.'.$format.'"',
        ]);
    }

    public function void(Request $request, Voucher $voucher)
    {
        $this->authorize('void', $voucher);

        $data = $request->validate([
            'void_reason' => ['required', 'string', 'min:1', 'max:255'],
        ]);

        if ($voucher->status === VoucherStatus::Voided) {
            return back()->withErrors(['voucher' => 'Voucher sudah dibatalkan.']);
        }

        $voucher->forceFill([
            'status' => VoucherStatus::Voided,
            'void_reason' => $data['void_reason'],
            'voided_by' => $request->user()->id,
            'voided_at' => now(),
        ])->save();

        Audit::record('voucher.voided', $voucher, ['reason' => $data['void_reason'], 'code' => Audit::maskCode($voucher->code)]);

        return back()->with('status', 'Voucher '.$voucher->code.' dibatalkan.');
    }
}
