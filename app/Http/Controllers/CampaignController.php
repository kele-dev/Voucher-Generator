<?php

namespace App\Http\Controllers;

use App\Enums\VoucherKind;
use App\Enums\VoucherType;
use App\Models\Brand;
use App\Models\Campaign;
use App\Models\Outlet;
use App\Models\VoucherTemplate;
use App\Services\Voucher\CodePattern;
use App\Services\Voucher\VoucherContent;
use App\Services\Voucher\VoucherRenderer;
use App\Support\Audit;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class CampaignController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', Campaign::class);

        $query = Campaign::query()
            ->visibleTo($request->user())
            ->with('brand')
            ->withCount('vouchers')
            ->latest('id');

        if ($request->filled('brand_id')) {
            $query->where('brand_id', $request->integer('brand_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        return view('campaigns.index', [
            'campaigns' => $query->paginate(15)->withQueryString(),
            'brands' => Brand::query()->orderBy('name')->get(),
            'statuses' => \App\Enums\CampaignStatus::cases(),
        ]);
    }

    public function create()
    {
        $this->authorize('create', Campaign::class);

        return view('campaigns.form', $this->formData());
    }

    public function store(Request $request)
    {
        $this->authorize('create', Campaign::class);

        $data = $this->validated($request);
        $pattern = $this->resolvePattern($request, $data['code_prefix']);
        $this->ensureTemplateMatchesBrand($data);
        $this->ensureOutletsMatchBrand($request, $data);

        $campaign = Campaign::create([
            ...$this->attributes($request, $data, $pattern),
            'status' => \App\Enums\CampaignStatus::Draft,
            'created_by' => $request->user()->id,
        ]);

        $this->syncOutlets($campaign, $request, $data);

        if ($request->hasFile('product_image')) {
            $campaign->product_image_path = $request->file('product_image')->store('products', (string) config('voucher.templates.disk'));
            $campaign->save();
        }

        Audit::record('campaign.created', $campaign, ['name' => $campaign->name]);

        return redirect()->route('campaigns.show', $campaign)->with('status', 'Campaign '.$campaign->name.' dibuat.');
    }

    public function show(Campaign $campaign)
    {
        $this->authorize('view', $campaign);

        $campaign->load(['brand', 'template', 'outlets']);

        $counts = collect([
            'issued' => $campaign->vouchers()->where('status', 'issued')->count(),
            'voided' => $campaign->vouchers()->where('status', 'voided')->count(),
        ]);

        $expiredCount = $campaign->vouchers()
            ->where('status', 'issued')
            ->whereNotNull('valid_until')
            ->where('valid_until', '<', now())
            ->count();

        $pattern = CodePattern::parse((string) $campaign->code_pattern);
        $skeleton = $pattern->hasSequence()
            ? $pattern->skeleton((string) $campaign->code_prefix, CarbonImmutable::now())
            : null;
        $lastSequence = $skeleton !== null
            ? (int) DB::table('code_sequences')->where('key', $skeleton)->value('last_value')
            : 0;

        return view('campaigns.show', [
            'campaign' => $campaign,
            'counts' => $counts,
            'expiredCount' => $expiredCount,
            'batches' => $campaign->batches()->with('creator')->latest('id')->get(),
            'remainingQuota' => $campaign->generation_limit !== null
                ? max(0, (int) $campaign->generation_limit - $campaign->committedCount())
                : null,
            'seqRemaining' => $pattern->hasSequence()
                ? max(0, $pattern->capacity() - $lastSequence)
                : null,
            'example' => $pattern->render((string) $campaign->code_prefix, CarbonImmutable::now(), $lastSequence + 1),
            'pattern' => $pattern,
        ]);
    }

    public function edit(Campaign $campaign)
    {
        $this->authorize('update', $campaign);

        return view('campaigns.form', $this->formData($campaign->load('outlets')));
    }

    public function update(Request $request, Campaign $campaign)
    {
        $this->authorize('update', $campaign);

        $data = $this->validated($request);
        $pattern = $this->resolvePattern($request, $data['code_prefix']);
        $this->ensureTemplateMatchesBrand($data);
        $this->ensureOutletsMatchBrand($request, $data);

        $errors = [];

        if ($campaign->hasIssuedVouchers()) {
            if ((int) $data['voucher_template_id'] !== (int) $campaign->voucher_template_id) {
                $errors['voucher_template_id'] = 'Template tidak bisa diganti setelah voucher terbit.';
            }

            if ((int) $data['brand_id'] !== (int) $campaign->brand_id) {
                $errors['brand_id'] = 'Brand tidak bisa diganti setelah voucher terbit.';
            }
        }

        if (array_key_exists('generation_limit', $data) && $data['generation_limit'] !== null
            && (int) $data['generation_limit'] < $campaign->committedCount()) {
            $errors['generation_limit'] = 'Batas total tidak boleh kurang dari jumlah voucher yang sudah/akan dibuat ('.$campaign->committedCount().').';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $campaign->fill($this->attributes($request, $data, $pattern))->save();

        $this->syncOutlets($campaign, $request, $data);

        if ($request->hasFile('product_image')) {
            $campaign->product_image_path = $request->file('product_image')->store('products', (string) config('voucher.templates.disk'));
            $campaign->save();
        }

        Audit::record('campaign.updated', $campaign, ['name' => $campaign->name]);

        return redirect()->route('campaigns.show', $campaign)->with('status', 'Campaign '.$campaign->name.' diperbarui.');
    }

    public function updateStatus(Request $request, Campaign $campaign)
    {
        $this->authorize('changeStatus', $campaign);

        $data = $request->validate([
            'status' => ['required', Rule::enum(\App\Enums\CampaignStatus::class)],
        ]);

        $target = \App\Enums\CampaignStatus::from($data['status']);

        if (! $campaign->status->canTransitionTo($target)) {
            throw ValidationException::withMessages([
                'campaign' => 'Perubahan status tidak diizinkan.',
            ]);
        }

        $campaign->update(['status' => $target]);

        Audit::record('campaign.status_changed', $campaign, ['status' => $target->value]);

        return back()->with('status', 'Status campaign diperbarui.');
    }

    public function destroy(Campaign $campaign)
    {
        $this->authorize('delete', $campaign);

        if ($campaign->hasIssuedVouchers()) {
            abort(403, 'Campaign yang sudah menerbitkan voucher tidak bisa dihapus.');
        }

        Audit::record('campaign.deleted', $campaign, ['name' => $campaign->name]);

        $campaign->delete();

        return redirect('/campaigns')->with('status', 'Campaign dihapus.');
    }

    public function preview(Request $request, VoucherRenderer $renderer)
    {
        $this->authorize('create', Campaign::class);

        $data = $request->validate([
            'voucher_template_id' => ['required', 'integer', 'exists:voucher_templates,id'],
        ]);

        $template = VoucherTemplate::findOrFail($data['voucher_template_id']);

        $content = VoucherContent::fromInput($request->all());
        $scope = $this->outletScope($request, $template);

        $values = array_merge(VoucherContent::texts($content), [
            'voucher_code' => (string) ($request->input('code_example') ?: 'BB-VM0810ABC1'),
            'campaign_title' => (string) $request->input('name', ''),
            'benefit_title' => (string) $request->input('benefit_title', ''),
            'benefit_description' => (string) $request->input('benefit_description', ''),
            'outlet_scope' => $scope,
            'outlet_scope_text' => $scope,
            'terms' => (string) $request->input('terms', ''),
            'expires_at' => $this->parseDate($request->input('expires_at')) ?? CarbonImmutable::now()->addMonth(),
            'product_image' => null,
        ]);

        $image = $renderer->encodeValues($template, $values, 'jpg');

        return response($image, 200, ['Content-Type' => 'image/jpeg']);
    }

    public function savedPreview(Campaign $campaign, VoucherRenderer $renderer)
    {
        abort_unless(request()->user()->isManager(), 403);

        $campaign->load(['brand', 'template']);

        $content = VoucherContent::texts(VoucherContent::snapshot($campaign));
        $scope = $campaign->outletScopeText();

        $values = array_merge($content, [
            'voucher_code' => 'BB-VM0810ABC1',
            'campaign_title' => (string) $campaign->name,
            'benefit_title' => (string) $campaign->benefit_title,
            'benefit_description' => (string) $campaign->benefit_description,
            'outlet_scope' => $scope,
            'outlet_scope_text' => $scope,
            'terms' => (string) $campaign->terms,
            'expires_at' => $campaign->expires_at ?? CarbonImmutable::now()->addMonth(),
            'valid_from' => $campaign->starts_at,
            'valid_until' => $campaign->expires_at,
            'product_image' => $campaign->product_image_path,
        ]);

        $image = $renderer->encodeValues($campaign->template, $values, 'jpg');

        return response($image, 200, ['Content-Type' => 'image/jpeg']);
    }

    public function productImage(Campaign $campaign)
    {
        $this->authorize('view', $campaign);

        abort_if(blank($campaign->product_image_path), 404);

        return Storage::disk((string) config('voucher.templates.disk'))->response($campaign->product_image_path);
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(?Campaign $campaign = null): array
    {
        $presets = config('voucher.codes.presets');

        $templates = VoucherTemplate::query()
            ->orderBy('brand_id')
            ->orderBy('name')
            ->orderBy('version')
            ->get()
            ->map(fn (VoucherTemplate $template): array => [
                'id' => $template->id,
                'name' => $template->name,
                'version' => $template->version,
                'brand_id' => $template->brand_id,
                'editor_url' => route('templates.editor', $template),
                'slots' => $template->fieldKeys(),
            ])->values();

        $outlets = Outlet::query()
            ->orderBy('name')
            ->get()
            ->map(fn (Outlet $outlet): array => [
                'id' => $outlet->id,
                'brand_id' => $outlet->brand_id,
                'name' => $outlet->name,
            ])->values();

        return [
            'campaign' => $campaign ?? new Campaign,
            'locked' => $campaign?->hasIssuedVouchers() ?? false,
            'brands' => Brand::query()->orderBy('name')->get(),
            'templates' => $templates,
            'outlets' => $outlets,
            'presets' => $presets,
            'preset' => $this->presetFor($campaign?->code_pattern, $presets),
            'voucherKinds' => VoucherKind::cases(),
            'voucherTypes' => VoucherType::cases(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $request->merge(['code_prefix' => Str::upper(trim((string) $request->input('code_prefix')))]);

        $presets = array_keys(config('voucher.codes.presets'));

        return $request->validate([
            'brand_id' => ['required', 'integer', 'exists:brands,id'],
            'voucher_kind' => ['nullable', Rule::enum(VoucherKind::class)],
            'voucher_type' => ['nullable', Rule::enum(VoucherType::class)],
            'voucher_template_id' => ['required', 'integer', 'exists:voucher_templates,id'],
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'],
            'benefit_title' => ['required', 'string', 'max:100'],
            'benefit_description' => ['nullable', 'string', 'max:200'],
            'terms' => ['nullable', 'string', 'max:1000'],
            'normal_value' => ['nullable', 'integer', 'min:0'],
            'min_purchase' => ['nullable', 'integer', 'min:0'],
            'service_type' => ['nullable', Rule::in(array_keys(VoucherContent::SERVICE_TYPES))],
            'valid_days' => ['nullable', 'array'],
            'valid_days.*' => ['integer', 'between:1,7'],
            'valid_time_from' => ['nullable', 'date_format:H:i'],
            'valid_time_until' => ['nullable', 'date_format:H:i', 'required_with:valid_time_from', 'after:valid_time_from'],
            'menu_items' => ['nullable', 'array', 'max:'.VoucherContent::MAX_MENU_ITEMS],
            'menu_items.*.name' => ['nullable', 'string', 'max:60'],
            'menu_items.*.qty' => ['nullable', 'integer', 'min:1', 'max:99'],
            'starts_at' => ['required', 'date'],
            'expires_at' => ['required', 'date', 'after:starts_at'],
            'code_prefix' => ['required', 'string', 'max:30', 'regex:/^[A-Z0-9]+(?:-[A-Z0-9]+)*$/'],
            'code_preset' => ['required', 'string', Rule::in([...$presets, 'custom'])],
            'code_pattern' => ['nullable', 'string', 'max:60'],
            'applies_to_all_outlets' => ['boolean'],
            'outlet_ids' => [
                'nullable', 'array',
                Rule::requiredIf(fn (): bool => ! $request->boolean('applies_to_all_outlets')),
            ],
            'outlet_ids.*' => ['integer', 'exists:outlets,id'],
            'generation_limit' => ['nullable', 'integer', 'min:1'],
            'product_image' => ['nullable', 'image', 'max:'.(int) config('voucher.templates.max_upload_kb')],
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(Request $request, array $data, string $pattern): array
    {
        $content = VoucherContent::fromInput($request->all());

        return [
            'brand_id' => $data['brand_id'],
            'voucher_kind' => $data['voucher_kind'] ?? VoucherKind::Physical->value,
            'voucher_type' => $data['voucher_type'] ?? VoucherType::VoucherMakan->value,
            'voucher_template_id' => $data['voucher_template_id'],
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'benefit_title' => $data['benefit_title'],
            'benefit_description' => $data['benefit_description'] ?? null,
            'terms' => $data['terms'] ?? null,
            'starts_at' => $data['starts_at'],
            'expires_at' => $data['expires_at'],
            'code_prefix' => $data['code_prefix'],
            'code_pattern' => $pattern,
            'applies_to_all_outlets' => $request->boolean('applies_to_all_outlets'),
            'generation_limit' => $data['generation_limit'] ?? null,
            'menu_items' => $content['menu_items'],
            'min_purchase' => $content['min_purchase'],
            'normal_value' => $content['normal_value'],
            'service_type' => $content['service_type'],
            'valid_days' => $content['valid_days'],
            'valid_time_from' => $content['valid_time_from'],
            'valid_time_until' => $content['valid_time_until'],
        ];
    }

    /**
     * Resolve the preset/custom pattern, validating it and its generated length.
     *
     * @param  array<string, mixed>  $data
     */
    private function resolvePattern(Request $request, string $prefix): string
    {
        $presets = config('voucher.codes.presets');
        $preset = (string) $request->input('code_preset');

        if ($preset === 'custom') {
            $pattern = Str::upper(trim((string) $request->input('code_pattern')));
        } else {
            $pattern = (string) ($presets[$preset]['pattern'] ?? '');
        }

        try {
            $parsed = CodePattern::parse($pattern);
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['code_pattern' => $e->getMessage()]);
        }

        $example = $parsed->render($prefix, CarbonImmutable::now(), 1);
        $maxLength = (int) config('voucher.codes.max_length', 40);

        if (strlen($example) > $maxLength) {
            throw ValidationException::withMessages([
                'code_pattern' => "Kode yang dihasilkan terlalu panjang ({$maxLength} karakter maksimum).",
            ]);
        }

        return $pattern;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function syncOutlets(Campaign $campaign, Request $request, array $data): void
    {
        $campaign->outlets()->sync($request->boolean('applies_to_all_outlets') ? [] : ($data['outlet_ids'] ?? []));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function ensureOutletsMatchBrand(Request $request, array $data): void
    {
        if ($request->boolean('applies_to_all_outlets')) {
            return;
        }

        $outletIds = $data['outlet_ids'] ?? [];

        if ($outletIds === []) {
            return;
        }

        $valid = Outlet::query()
            ->whereIn('id', $outletIds)
            ->where('brand_id', (int) $data['brand_id'])
            ->pluck('id')
            ->all();

        if (count($valid) !== count(array_unique($outletIds))) {
            throw ValidationException::withMessages([
                'outlet_ids' => 'Outlet harus berasal dari brand yang sama.',
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function ensureTemplateMatchesBrand(array $data): void
    {
        $template = VoucherTemplate::find($data['voucher_template_id']);

        if ($template !== null && (int) $template->brand_id !== (int) $data['brand_id']) {
            throw ValidationException::withMessages([
                'voucher_template_id' => 'Template harus milik brand yang dipilih.',
            ]);
        }
    }

    private function outletScope(Request $request, VoucherTemplate $template): string
    {
        if ($request->boolean('applies_to_all_outlets')) {
            $brand = Brand::find($request->input('brand_id')) ?? $template->brand;

            return $brand ? 'BERLAKU DI SEMUA CABANG '.mb_strtoupper($brand->name) : 'BERLAKU DI SEMUA CABANG';
        }

        $names = Outlet::query()->whereIn('id', (array) $request->input('outlet_ids', []))->pluck('name')->all();

        return $names === [] ? 'BERLAKU DI OUTLET TERPILIH' : 'BERLAKU DI: '.implode(', ', $names);
    }

    private function presetFor(?string $pattern, array $presets): string
    {
        if ($pattern === null) {
            return 'example_random';
        }

        foreach ($presets as $key => $preset) {
            if (($preset['pattern'] ?? null) === $pattern) {
                return $key;
            }
        }

        return 'custom';
    }

    private function parseDate(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
