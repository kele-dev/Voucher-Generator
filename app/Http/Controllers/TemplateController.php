<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\VoucherTemplate;
use App\Services\Voucher\VoucherRenderer;
use App\Support\Audit;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use RuntimeException;

class TemplateController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', VoucherTemplate::class);

        $query = VoucherTemplate::query()->with('brand')->orderBy('brand_id')->orderBy('name')->orderBy('version');

        if ($request->filled('brand_id')) {
            $query->where('brand_id', $request->integer('brand_id'));
        }

        return view('templates.index', [
            'templates' => $query->paginate(18)->withQueryString(),
            'brands' => Brand::query()->orderBy('name')->get(),
        ]);
    }

    public function create()
    {
        $this->authorize('create', VoucherTemplate::class);

        return view('templates.form', [
            'template' => new VoucherTemplate,
            'brands' => Brand::query()->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', VoucherTemplate::class);

        $data = $request->validate([
            'brand_id' => ['required', 'integer', 'exists:brands,id'],
            'name' => ['required', 'string', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
            'image' => [
                'required', 'image',
                'max:'.(int) config('voucher.templates.max_upload_kb'),
                'dimensions:min_width=600,min_height=250',
            ],
        ]);

        $file = $request->file('image');
        $path = $file->store('templates', (string) config('voucher.templates.disk'));
        $size = @getimagesize($file->getRealPath()) ?: [0, 0];

        $template = $this->createVersionRecord(
            (int) $data['brand_id'],
            (string) $data['name'],
            $path,
            (int) $size[0],
            (int) $size[1],
            $request->boolean('is_active', true),
        );

        Audit::record('template.created', $template, ['name' => $template->name, 'version' => $template->version]);

        return redirect()->route('templates.editor', $template)->with('status', 'Template diunggah.');
    }

    public function edit(VoucherTemplate $template)
    {
        $this->authorize('update', $template);

        return view('templates.form', [
            'template' => $template,
            'brands' => Brand::query()->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, VoucherTemplate $template)
    {
        $this->authorize('update', $template);

        $data = $request->validate([
            'brand_id' => ['required', 'integer', 'exists:brands,id'],
            'name' => ['required', 'string', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
            'image' => [
                'nullable', 'image',
                'max:'.(int) config('voucher.templates.max_upload_kb'),
                'dimensions:min_width=600,min_height=250',
            ],
        ]);

        $template->fill([
            'brand_id' => $data['brand_id'],
            'name' => $data['name'],
            'is_active' => $request->boolean('is_active'),
        ]);

        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $path = $file->store('templates', (string) config('voucher.templates.disk'));
            $size = @getimagesize($file->getRealPath()) ?: [0, 0];

            $template->image_path = $path;
            $template->image_width = (int) $size[0];
            $template->image_height = (int) $size[1];
        }

        $template->save();

        Audit::record('template.updated', $template, ['name' => $template->name, 'version' => $template->version]);

        return redirect()->route('templates.editor', $template)->with('status', 'Template diperbarui.');
    }

    public function destroy(VoucherTemplate $template)
    {
        $this->authorize('delete', $template);

        if ($template->campaigns()->exists()) {
            return back()->withErrors(['template' => 'Template masih dipakai oleh campaign.']);
        }

        $image = (string) $template->image_path;

        Audit::record('template.deleted', $template, ['name' => $template->name, 'version' => $template->version]);

        $template->delete();

        // A previous version may still share the image file.
        if ($image !== '' && ! VoucherTemplate::query()->where('image_path', $image)->exists()) {
            Storage::disk((string) config('voucher.templates.disk'))->delete($image);
        }

        return redirect('/templates')->with('status', 'Template dihapus.');
    }

    public function image(VoucherTemplate $template)
    {
        $this->authorize('view', $template);

        return Storage::disk((string) config('voucher.templates.disk'))->response($template->image_path);
    }

    public function sampleImage(VoucherTemplate $template)
    {
        $this->authorize('view', $template);

        abort_if(blank($template->sample_image_path), 404);

        return Storage::disk((string) config('voucher.templates.disk'))->response($template->sample_image_path);
    }

    public function uploadSampleImage(Request $request, VoucherTemplate $template)
    {
        $this->authorize('update', $template);

        $maxWidth = (int) config('voucher.templates.overlay_max_width');

        $request->validate([
            'sample_image' => [
                'required', 'image',
                'max:'.(int) config('voucher.templates.max_upload_kb'),
                'dimensions:max_width='.$maxWidth,
            ],
        ]);

        $path = $request->file('sample_image')->store('samples', (string) config('voucher.templates.disk'));

        $template->forceFill(['sample_image_path' => $path])->save();

        return response()->json([
            'message' => 'Gambar contoh diunggah.',
            'url' => route('templates.sample-image', $template),
        ]);
    }

    public function editor(VoucherTemplate $template)
    {
        $this->authorize('view', $template);

        return view('templates.editor', [
            'template' => $template->load('brand'),
            'fieldConfig' => config('voucher.fields'),
            'fonts' => config('voucher.fonts'),
            'samples' => $this->samples($template),
            'canEdit' => auth()->user()->can('update', $template),
        ]);
    }

    public function updateFields(Request $request, VoucherTemplate $template)
    {
        $this->authorize('update', $template);

        $fields = $this->normaliseFields($request->input('fields', []));

        $template->forceFill([
            'fields_json' => ['schema_version' => 1, 'fields' => $fields],
        ])->save();

        Audit::record('template.fields_updated', $template, ['count' => count($fields)]);

        return response()->json(['fields' => $fields]);
    }

    public function preview(Request $request, VoucherTemplate $template, VoucherRenderer $renderer)
    {
        $this->authorize('view', $template);

        $fields = $this->normaliseFields($request->input('fields', []));

        $preview = new VoucherTemplate([
            'brand_id' => $template->brand_id,
            'name' => $template->name,
            'version' => $template->version,
            'image_path' => $template->image_path,
            'image_width' => $template->image_width,
            'image_height' => $template->image_height,
            'sample_image_path' => $template->sample_image_path,
            'fields_json' => ['schema_version' => 1, 'fields' => $fields],
        ]);

        $image = $renderer->encodeValues($preview, $this->samples($template), 'jpg');

        return response($image, 200, ['Content-Type' => 'image/jpeg']);
    }

    public function createVersion(VoucherTemplate $template)
    {
        $this->authorize('createVersion', $template);

        $copy = $this->createVersionRecord(
            (int) $template->brand_id,
            (string) $template->name,
            (string) $template->image_path,
            (int) $template->image_width,
            (int) $template->image_height,
            (bool) $template->is_active,
            $template->fields_json,
        );

        Audit::record('template.version_created', $copy, ['version' => $copy->version]);

        return redirect()->route('templates.editor', $copy)->with('status', 'Versi baru dibuat.');
    }

    /**
     * @param  array<string, mixed>|null  $fieldsJson
     */
    private function createVersionRecord(
        int $brandId,
        string $name,
        string $imagePath,
        int $width,
        int $height,
        bool $active,
        ?array $fieldsJson = null,
    ): VoucherTemplate {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $version = (int) VoucherTemplate::query()
                ->where('brand_id', $brandId)
                ->where('name', $name)
                ->max('version') + 1;

            try {
                return VoucherTemplate::create([
                    'brand_id' => $brandId,
                    'name' => $name,
                    'version' => $version,
                    'image_path' => $imagePath,
                    'image_width' => $width,
                    'image_height' => $height,
                    'fields_json' => $fieldsJson ?? ['schema_version' => 1, 'fields' => []],
                    'is_active' => $active,
                ]);
            } catch (QueryException) {
                // Another request grabbed this version number; recompute.
            }
        }

        throw new RuntimeException('Gagal membuat versi template baru.');
    }

    /**
     * Validate and normalise the editor payload into the stored shape.
     *
     * @return array<int, array<string, mixed>>
     */
    private function normaliseFields(mixed $input): array
    {
        $input = is_array($input) ? array_values($input) : [];
        $config = config('voucher.fields');
        $fonts = config('voucher.fonts');

        $validator = Validator::make(['fields' => $input], [
            'fields' => ['present', 'array'],
            'fields.*' => ['array'],
            'fields.*.key' => ['required', 'string'],
            'fields.*.x' => ['required', 'numeric', 'min:0', 'lt:1'],
            'fields.*.y' => ['required', 'numeric', 'min:0', 'lt:1'],
            'fields.*.width' => ['required', 'numeric', 'gt:0', 'lte:1'],
            'fields.*.height' => ['required', 'numeric', 'gt:0', 'lte:1'],
        ]);

        $validator->after(function ($validator) use ($input, $config, $fonts): void {
            $seen = [];

            foreach ($input as $i => $field) {
                if (! is_array($field)) {
                    continue;
                }

                $key = $field['key'] ?? null;

                if (! is_string($key) || ! isset($config[$key])) {
                    $validator->errors()->add("fields.$i.key", 'Slot tidak dikenal.');

                    continue;
                }

                if (isset($seen[$key])) {
                    $validator->errors()->add("fields.$i.key", 'Slot tidak boleh dipasang dua kali.');
                }

                $seen[$key] = true;

                $type = $field['type'] ?? 'text';

                // Slots carry their render type: images are "image", and every
                // other config type (plain text and date alike) renders as
                // "text". The semantic "date" type lives only in config.
                $expectedType = $config[$key]['type'] === 'image' ? 'image' : 'text';

                if ($type !== $expectedType) {
                    $validator->errors()->add("fields.$i.type", 'Jenis slot tidak cocok.');
                }

                $x = (float) ($field['x'] ?? 0);
                $y = (float) ($field['y'] ?? 0);
                $w = (float) ($field['width'] ?? 0);
                $h = (float) ($field['height'] ?? 0);

                if ($x + $w > 1.0001) {
                    $validator->errors()->add("fields.$i.width", 'Kotak keluar dari kanvas.');
                }

                if ($y + $h > 1.0001) {
                    $validator->errors()->add("fields.$i.height", 'Kotak keluar dari kanvas.');
                }

                if ($type === 'image') {
                    $fit = $field['fit'] ?? 'contain';

                    if (! in_array($fit, ['contain', 'cover'], true)) {
                        $validator->errors()->add("fields.$i.fit", 'Mode gambar tidak dikenal.');
                    }

                    continue;
                }

                $family = $field['font_family'] ?? 'poppins';

                if (! isset($fonts[$family])) {
                    $validator->errors()->add("fields.$i.font_family", 'Font tidak dikenal.');
                }

                $weight = $field['font_weight'] ?? 'regular';

                if (! in_array($weight, $fonts[$family]['weights'] ?? [], true)) {
                    $validator->errors()->add("fields.$i.font_weight", 'Ketebalan font tidak dikenal.');
                }

                $size = (int) ($field['font_size'] ?? 24);

                if ($size < 8 || $size > 400) {
                    $validator->errors()->add("fields.$i.font_size", 'Ukuran font di luar batas 8–400.');
                }

                $color = $field['color'] ?? '#000000';

                if (! is_string($color) || ! preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $color)) {
                    $validator->errors()->add("fields.$i.color", 'Warna harus format heksadesimal.');
                }

                $align = $field['align'] ?? 'center';

                if (! in_array($align, ['left', 'center', 'right'], true)) {
                    $validator->errors()->add("fields.$i.align", 'Perataan tidak dikenal.');
                }
            }
        });

        $validator->validate();

        return $this->canonicalFields($input, $config);
    }

    /**
     * @param  array<int, array<string, mixed>>  $input
     * @param  array<string, array<string, mixed>>  $config
     * @return array<int, array<string, mixed>>
     */
    private function canonicalFields(array $input, array $config): array
    {
        $out = [];

        foreach ($input as $field) {
            if (! is_array($field) || ! is_string($field['key'] ?? null) || ! isset($config[$field['key']])) {
                continue;
            }

            $key = $field['key'];
            $configType = $config[$key]['type'];
            $type = $configType === 'image' ? 'image' : 'text';

            $base = [
                'key' => $key,
                'type' => $type,
                'x' => round((float) $field['x'], 4),
                'y' => round((float) $field['y'], 4),
                'width' => round((float) $field['width'], 4),
                'height' => round((float) $field['height'], 4),
            ];

            if ($type === 'image') {
                $base['fit'] = $field['fit'] ?? 'contain';
            } else {
                $base['font_family'] = $field['font_family'] ?? 'poppins';
                $base['font_weight'] = $field['font_weight'] ?? 'regular';
                $base['font_size'] = (int) ($field['font_size'] ?? 24);
                $base['color'] = $field['color'] ?? '#000000';
                $base['align'] = $field['align'] ?? 'center';
                $base['vertical_align'] = $field['vertical_align'] ?? 'middle';
                $base['uppercase'] = (bool) ($field['uppercase'] ?? false);
                $base['cover_enabled'] = (bool) ($field['cover_enabled'] ?? false);

                if ($configType === 'date') {
                    $base['date_format'] = (string) ($field['date_format'] ?? 'j F Y');
                }

                if (! empty($field['cover_enabled'])) {
                    $base['cover_color'] = $field['cover_color'] ?? '#FFFFFF';
                }
            }

            $out[] = $base;
        }

        return $out;
    }

    /**
     * Sample values the editor draws while placing fields.
     *
     * @return array<string, mixed>
     */
    private function samples(VoucherTemplate $template): array
    {
        return [
            'product_image' => filled($template->sample_image_path)
                ? route('templates.sample-image', $template)
                : null,
            'voucher_code' => 'BB-VM0810ABC1',
            'benefit_title' => 'GRATIS 1 PORSI',
            'benefit_description' => 'NASI BEBEK + ES TEH',
            'expires_at' => CarbonImmutable::now()->addMonth()->toDateString(),
            'menu_items' => '1x Bebek Goreng · 1x Es Teh',
            'valid_schedule' => 'Sen–Jum · 10.00–20.00',
            'outlet_scope' => 'BERLAKU DI SEMUA CABANG',
            'outlet_scope_text' => 'BERLAKU DI SEMUA CABANG',
            'campaign_title' => 'Promo Contoh',
            'min_purchase' => 'Min. belanja Rp50.000',
            'normal_value' => 'Senilai Rp75.000',
            'terms' => 'Tidak dapat diuangkan.',
            'free_portion' => 'GRATIS 2 PORSI',
            'service_type' => 'Khusus dine-in',
        ];
    }
}
