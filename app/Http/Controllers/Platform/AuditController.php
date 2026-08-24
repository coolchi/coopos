<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Support\TenantContext;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AuditController extends Controller
{
    public function index(Request $request)
    {
        TenantContext::bypass();

        $logs = Activity::query()
            ->with('causer')
            ->when($request->string('q')->toString(), function ($query, string $q) {
                $query->where(function ($inner) use ($q) {
                    $inner->where('description', 'like', "%{$q}%")
                        ->orWhere('event', 'like', "%{$q}%")
                        ->orWhere('properties', 'like', "%{$q}%");
                });
            })
            ->latest()
            ->paginate(30)
            ->withQueryString()
            ->through(fn (Activity $log) => [
                'id' => $log->id,
                'description' => $log->description,
                'event' => $log->event,
                'subject' => class_basename((string) $log->subject_type) ?: '—',
                'causer' => $log->causer?->name ?? 'System',
                'device' => $log->getExtraProperty('device') ?: '—',
                'ip' => $log->getExtraProperty('ip'),
                'created_at' => $log->created_at?->toDayDateTimeString(),
            ]);

        return Inertia::render('Platform/Audit', [
            'logs' => $logs,
            'filters' => $request->only('q'),
        ]);
    }
}
