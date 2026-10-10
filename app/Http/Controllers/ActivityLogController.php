<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewActivity');

        $query = ActivityLog::query()->with('actor')->latest('id');

        if ($request->filled('action')) {
            $query->where('action', (string) $request->string('action'));
        }

        if ($request->filled('actor_id')) {
            $query->where('actor_id', $request->integer('actor_id'));
        }

        if ($request->filled('from')) {
            $query->where('created_at', '>=', CarbonImmutable::parse((string) $request->input('from'))->startOfDay());
        }

        if ($request->filled('to')) {
            $query->where('created_at', '<=', CarbonImmutable::parse((string) $request->input('to'))->endOfDay());
        }

        return view('activity.index', [
            'logs' => $query->paginate(30)->withQueryString(),
            'actions' => ActivityLog::query()->select('action')->distinct()->orderBy('action')->pluck('action'),
            'actors' => User::query()->orderBy('name')->get(),
        ]);
    }
}
