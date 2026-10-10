<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\VoucherTemplate;
use Illuminate\Http\Request;

class EditorController extends Controller
{
    public function index(Request $request)
    {
        $selectedBrandId = $request->integer('brand_id') ?: null;

        $templates = VoucherTemplate::query()
            ->with('brand')
            ->when($selectedBrandId, fn ($q, $id) => $q->where('brand_id', $id))
            ->orderBy('brand_id')
            ->orderBy('name')
            ->orderBy('version')
            ->get();

        return view('editor.index', [
            'brands' => Brand::query()->orderBy('name')->get(),
            'templates' => $templates,
            'selectedBrandId' => $selectedBrandId,
        ]);
    }

    public function show(VoucherTemplate $template)
    {
        return redirect()->route('templates.editor', $template);
    }
}
