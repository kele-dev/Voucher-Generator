<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\Brand;
use App\Models\Outlet;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OutletController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', Outlet::class);

        $query = Outlet::query()->with('brand')->orderBy('name');

        if ($request->user()->role === Role::OutletStaff) {
            $query->whereHas('users', fn ($q) => $q->where('users.id', $request->user()->id));
        }

        if ($request->filled('brand_id')) {
            $query->where('brand_id', $request->integer('brand_id'));
        }

        return view('outlets.index', [
            'outlets' => $query->paginate(20)->withQueryString(),
            'brands' => Brand::query()->orderBy('name')->get(),
        ]);
    }

    public function create()
    {
        $this->authorize('create', Outlet::class);

        return view('outlets.form', [
            'outlet' => new Outlet,
            'brands' => Brand::query()->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', Outlet::class);

        $data = $this->validated($request);

        $outlet = Outlet::create([
            'brand_id' => $data['brand_id'],
            'name' => $data['name'],
            'code' => $data['code'],
            'city' => $data['city'] ?? null,
            'is_active' => $request->boolean('is_active'),
        ]);

        Audit::record('outlet.created', $outlet, ['name' => $outlet->name, 'code' => $outlet->code]);

        return redirect('/outlets')->with('status', 'Outlet '.$outlet->name.' disimpan.');
    }

    public function edit(Outlet $outlet)
    {
        $this->authorize('update', $outlet);

        return view('outlets.form', [
            'outlet' => $outlet,
            'brands' => Brand::query()->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Outlet $outlet)
    {
        $this->authorize('update', $outlet);

        $data = $this->validated($request, $outlet);

        $outlet->fill([
            'brand_id' => $data['brand_id'],
            'name' => $data['name'],
            'code' => $data['code'],
            'city' => $data['city'] ?? null,
            'is_active' => $request->boolean('is_active'),
        ])->save();

        Audit::record('outlet.updated', $outlet, ['name' => $outlet->name, 'code' => $outlet->code]);

        return redirect('/outlets')->with('status', 'Outlet '.$outlet->name.' diperbarui.');
    }

    public function destroy(Outlet $outlet)
    {
        $this->authorize('delete', $outlet);

        Audit::record('outlet.deleted', $outlet, ['name' => $outlet->name, 'code' => $outlet->code]);

        $outlet->delete();

        return redirect('/outlets')->with('status', 'Outlet dihapus.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Outlet $outlet = null): array
    {
        $request->merge(['code' => strtoupper(trim((string) $request->input('code')))]);

        return $request->validate([
            'brand_id' => ['required', 'integer', 'exists:brands,id'],
            'name' => ['required', 'string', 'max:100'],
            'code' => [
                'required', 'string', 'max:32',
                'regex:/^[A-Z0-9]+(?:-[A-Z0-9]+)*$/',
                Rule::unique('outlets', 'code')->ignore($outlet?->id),
            ],
            'city' => ['nullable', 'string', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
        ]);
    }
}
