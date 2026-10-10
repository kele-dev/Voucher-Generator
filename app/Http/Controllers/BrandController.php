<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Outlet;
use App\Models\VoucherTemplate;
use App\Support\Audit;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class BrandController extends Controller
{
    public function index()
    {
        $brands = Brand::query()->withCount('outlets')->orderBy('name')->paginate(20)->withQueryString();

        return view('brands.index', ['brands' => $brands]);
    }

    public function create()
    {
        $this->authorize('create', Brand::class);

        return view('brands.form', ['brand' => new Brand]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', Brand::class);

        $data = $this->validated($request);

        $brand = Brand::create([
            'name' => $data['name'],
            'slug' => $data['slug'],
            'code_prefix' => $data['code_prefix'],
            'is_active' => $request->boolean('is_active'),
        ]);

        $this->syncTemplates($brand, $request);

        Audit::record('brand.created', $brand);

        return redirect('/brands')->with('status', 'Brand '.$brand->name.' disimpan.');
    }

    public function edit(Brand $brand)
    {
        $this->authorize('update', $brand);

        return view('brands.form', ['brand' => $brand]);
    }

    public function update(Request $request, Brand $brand)
    {
        $this->authorize('update', $brand);

        $data = $this->validated($request, $brand);

        $brand->fill([
            'name' => $data['name'],
            'slug' => $data['slug'],
            'code_prefix' => $data['code_prefix'],
            'is_active' => $request->boolean('is_active'),
        ])->save();

        $this->syncTemplates($brand, $request);

        Audit::record('brand.updated', $brand, ['changes' => ['name' => $brand->name]]);

        return redirect('/brands')->with('status', 'Brand '.$brand->name.' diperbarui.');
    }

    public function destroy(Brand $brand)
    {
        $this->authorize('delete', $brand);

        if ($brand->outlets()->exists() || $brand->templates()->exists()) {
            return back()->withErrors([
                'brand' => 'Brand masih dipakai outlet atau template sehingga tidak bisa dihapus.',
            ]);
        }

        Audit::record('brand.deleted', $brand);
        $brand->delete();

        return redirect('/brands')->with('status', 'Brand dihapus.');
    }

    public function templateImage(Brand $brand, string $kind)
    {
        abort_unless(in_array($kind, ['physical', 'electronic', 'nominal', 'electronic_nominal'], true), 404);

        $path = $brand->templatePath($kind);
        abort_if(blank($path), 404);

        $disk = Storage::disk(config('voucher.templates.disk'));
        abort_unless($disk->exists($path), 404);

        return $disk->response($path);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Brand $brand = null): array
    {
        $request->merge([
            'code_prefix' => strtoupper(trim((string) $request->input('code_prefix'))),
        ]);

        if (blank($request->input('slug'))) {
            $request->merge(['slug' => Str::slug((string) $request->input('name'))]);
        }

        $maxKb = (int) config('voucher.templates.max_upload_kb', 5120);

        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'slug' => [
                'required', 'string', 'max:120',
                Rule::unique('brands', 'slug')->ignore($brand?->id),
            ],
            'code_prefix' => ['required', 'string', 'max:10', 'regex:/^[A-Z0-9]+(?:-[A-Z0-9]+)*$/'],
            'is_active' => ['nullable', 'boolean'],
            'template_physical' => ['nullable', 'image', 'max:'.$maxKb],
            'template_electronic' => ['nullable', 'image', 'max:'.$maxKb],
            'template_nominal' => ['nullable', 'image', 'max:'.$maxKb],
            'template_electronic_nominal' => ['nullable', 'image', 'max:'.$maxKb],
        ]);
    }

    private function syncTemplates(Brand $brand, Request $request): void
    {
        $kinds = [
            'physical' => 'Voucher Fisik',
            'electronic' => 'e-Voucher Fisik',
            'nominal' => 'Voucher Nominal',
            'electronic_nominal' => 'e-Voucher Nominal',
        ];

        $disk = (string) config('voucher.templates.disk', 'local');

        foreach ($kinds as $kind => $name) {
            $file = $request->file('template_'.$kind);

            if ($file === null) {
                continue;
            }

            $path = $file->store('templates', $disk);
            $size = @getimagesize($file->getRealPath()) ?: [0, 0];

            $brand->forceFill(['template_'.$kind.'_path' => $path])->save();

            $this->createTemplateVersion($brand, $name, $path, (int) $size[0], (int) $size[1]);
        }
    }

    private function createTemplateVersion(Brand $brand, string $name, string $path, int $width, int $height): VoucherTemplate
    {
        for ($attempt = 0; $attempt < 3; $attempt++) {
            $version = (int) VoucherTemplate::query()
                ->where('brand_id', $brand->id)
                ->where('name', $name)
                ->max('version') + 1;

            try {
                return VoucherTemplate::create([
                    'brand_id' => $brand->id,
                    'name' => $name,
                    'version' => $version,
                    'image_path' => $path,
                    'image_width' => $width,
                    'image_height' => $height,
                    'fields_json' => VoucherTemplate::wrapFields([]),
                    'is_active' => true,
                ]);
            } catch (QueryException) {
                // Someone else won this version number; try the next one.
            }
        }

        throw new \RuntimeException('Gagal menyimpan template brand.');
    }
}
