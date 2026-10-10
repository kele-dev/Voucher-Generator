<?php

namespace App\Http\Controllers;

use App\Enums\CampaignStatus;
use App\Enums\Role;
use App\Models\Brand;
use App\Models\Campaign;
use App\Models\GenerationBatch;
use App\Models\User;
use App\Models\Voucher;
use App\Support\DashboardFilters;
use App\Support\QueueStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'brand_id' => ['nullable', 'integer'],
            'campaign_id' => ['nullable', 'integer'],
        ]);

        $filters = DashboardFilters::fromRequest($request);

        // Voucher and batch figures span the whole brand: outlet staff don't
        // get them, only their own campaigns' shelf life.
        $campaignWide = $user->role !== Role::OutletStaff;

        $campaignBase = fn (): Builder => Campaign::query()
            ->when($filters->brandId, fn (Builder $q, $id) => $q->where('brand_id', $id))
            ->when($filters->campaignId, fn (Builder $q, $id) => $q->whereKey($id));

        $voucherInScope = fn (): Builder => Voucher::query()
            ->whereHas('campaign', fn (Builder $q) => $q
                ->when($filters->brandId, fn (Builder $qq, $id) => $qq->where('brand_id', $id))
                ->when($filters->campaignId, fn (Builder $qq, $id) => $qq->whereKey($id)))
            ->when($filters->from, fn (Builder $q, $from) => $q->where('issued_at', '>=', CarbonImmutable::parse($from)->startOfDay()))
            ->when($filters->to, fn (Builder $q, $to) => $q->where('issued_at', '<=', CarbonImmutable::parse($to)->endOfDay()));

        $batchInScope = fn (): Builder => GenerationBatch::query()
            ->whereHas('campaign', fn (Builder $q) => $q
                ->when($filters->brandId, fn (Builder $qq, $id) => $qq->where('brand_id', $id))
                ->when($filters->campaignId, fn (Builder $qq, $id) => $qq->whereKey($id)))
            ->when($filters->from, fn (Builder $q, $from) => $q->where('created_at', '>=', CarbonImmutable::parse($from)->startOfDay()))
            ->when($filters->to, fn (Builder $q, $to) => $q->where('created_at', '<=', CarbonImmutable::parse($to)->endOfDay()));

        $metrics = [
            'issued' => null,
            'batches' => null,
            'campaigns' => $campaignBase()->count(),
            'active' => null,
            'expired' => null,
            'voided' => null,
            'users_active' => $user->isSuperAdmin() ? User::query()->where('is_active', true)->count() : null,
        ];

        if ($campaignWide) {
            $metrics['issued'] = $voucherInScope()->count();
            $metrics['batches'] = $batchInScope()->count();
            $metrics['active'] = $voucherInScope()
                ->where('status', 'issued')
                ->where(fn (Builder $q) => $q->whereNull('valid_until')->orWhere('valid_until', '>=', now()))
                ->count();
            $metrics['expired'] = $voucherInScope()
                ->where('status', 'issued')
                ->whereNotNull('valid_until')
                ->where('valid_until', '<', now())
                ->count();
            $metrics['voided'] = $voucherInScope()->where('status', 'voided')->count();
        }

        $campaignCounts = DB::table('voucher_campaigns')
            ->when($filters->brandId, fn ($q, $id) => $q->where('brand_id', $id))
            ->when($filters->campaignId, fn ($q, $id) => $q->where('id', $id))
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $campaigns = $campaignBase()
            ->with('brand')
            ->withCount('vouchers')
            ->latest('id')
            ->limit(8)
            ->get();

        return view('dashboard', [
            'filters' => $filters,
            'brands' => Brand::query()->orderBy('name')->get(),
            'campaignOptions' => Campaign::query()->orderBy('name')->get(),
            'metrics' => $metrics,
            'campaigns' => $campaigns,
            'campaignCounts' => $campaignCounts,
            'campaignWide' => $campaignWide,
            'statuses' => CampaignStatus::cases(),
            'queueStalled' => QueueStatus::stalled(),
        ]);
    }
}
