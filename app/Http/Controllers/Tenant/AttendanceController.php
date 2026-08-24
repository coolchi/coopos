<?php

namespace App\Http\Controllers\Tenant;

use App\Enums\MemberStatus;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Meeting;
use App\Models\Member;
use App\Support\BranchContext;
use App\Support\FiscalYearContext;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AttendanceController extends Controller
{
    public function index(Request $request)
    {
        $meetingsQuery = Meeting::query()->with('branch')->withCount([
            'attendances as present_count' => fn ($q) => $q->where('present', true),
            'attendances as permitted_count' => fn ($q) => $q->where('status', 'permitted'),
            'attendances',
        ]);
        FiscalYearContext::constrain($meetingsQuery, 'held_on');
        $meetings = $meetingsQuery->latest('held_on')->latest('id')->limit(60)->get();

        $eligibleByBranch = Member::query()
            ->where('status', MemberStatus::Active)
            ->selectRaw('branch_id, count(*) as total')
            ->groupBy('branch_id')
            ->pluck('total', 'branch_id');
        $eligibleAll = (int) Member::query()->where('status', MemberStatus::Active)->count();

        $rows = $meetings->map(function (Meeting $m) use ($eligibleByBranch, $eligibleAll) {
            $eligible = $m->branch_id
                ? (int) ($eligibleByBranch[$m->branch_id] ?? 0)
                : $eligibleAll;
            $present = (int) $m->present_count;
            $permitted = (int) $m->permitted_count;
            $marked = (int) $m->attendances_count;
            $rate = $eligible > 0 ? (int) round(($present / $eligible) * 100) : 0;

            return [
                'uuid' => $m->uuid,
                'title' => $m->title,
                'held_on' => $m->held_on?->format('d M Y'),
                'held_on_raw' => $m->held_on?->toDateString(),
                'kind' => $m->kind,
                'kind_label' => $this->kindLabel($m->kind),
                'branch' => $m->branch?->name,
                'present' => $present,
                'permitted' => $permitted,
                'marked' => $marked,
                'eligible' => $eligible,
                'rate' => $rate,
                'status' => $marked === 0 ? 'not_started' : ($marked >= $eligible && $eligible > 0 ? 'complete' : 'in_progress'),
            ];
        });

        $thisMonth = $rows->filter(fn ($m) => str_starts_with((string) $m['held_on_raw'], now()->format('Y-m')));
        $rollUuid = $request->query('roll');
        $rollMeeting = $rollUuid
            ? $meetings->firstWhere('uuid', $rollUuid) ?? Meeting::query()->where('uuid', $rollUuid)->first()
            : null;

        return Inertia::render('Tenant/Office/Attendance', [
            'meetings' => $rows->values(),
            'branches' => Branch::query()->where('is_active', true)->orderBy('name')->get(['uuid', 'name']),
            'counts' => [
                'meetings' => $rows->count(),
                'this_month' => $thisMonth->count(),
                'avg_rate' => $rows->where('marked', '>', 0)->avg('rate'),
                'members' => $eligibleAll,
            ],
            'roll' => $rollMeeting instanceof Meeting ? $this->rollPayload($rollMeeting) : null,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'held_on' => ['required', 'date'],
            'kind' => ['nullable', 'string', 'max:40'],
            'branch_uuid' => ['nullable', 'uuid'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $meeting = Meeting::query()->create([
            'title' => $data['title'],
            'held_on' => $data['held_on'],
            'kind' => $data['kind'] ?: 'general',
            'notes' => $data['notes'] ?? null,
            'branch_id' => ($data['branch_uuid'] ?? null)
                ? Branch::query()->where('uuid', $data['branch_uuid'])->value('id')
                : (BranchContext::isConstrained() ? BranchContext::id() : null),
        ]);

        return redirect()->route('tenant.attendance.index', [
            'tenant' => tenant()->slug,
            'roll' => $meeting->uuid,
        ])->with('success', 'Meeting created. Mark who attended.');
    }

    public function show(string $tenant, Meeting $meeting)
    {
        return redirect()->route('tenant.attendance.index', [
            'tenant' => $tenant,
            'roll' => $meeting->uuid,
        ]);
    }

    public function save(Request $request, string $tenant, Meeting $meeting)
    {
        $data = $request->validate([
            'attendance' => ['nullable', 'array'],
            'attendance.*' => ['in:present,absent,permitted'],
            'present' => ['nullable', 'array'],
            'present.*' => ['uuid'],
            'permitted' => ['nullable', 'array'],
            'permitted.*' => ['uuid'],
        ]);

        $present = collect($data['present'] ?? []);
        $permitted = collect($data['permitted'] ?? []);
        $map = $data['attendance'] ?? [];

        $members = Member::query()
            ->where('status', MemberStatus::Active)
            ->when($meeting->branch_id, fn ($q) => $q->where('branch_id', $meeting->branch_id))
            ->get();

        foreach ($members as $member) {
            $status = $map[$member->uuid] ?? null;
            if (! in_array($status, ['present', 'absent', 'permitted'], true)) {
                $status = $permitted->contains($member->uuid)
                    ? 'permitted'
                    : ($present->contains($member->uuid) ? 'present' : 'absent');
            }

            $meeting->attendances()->updateOrCreate(
                ['member_id' => $member->id],
                [
                    'tenant_id' => $meeting->tenant_id,
                    'present' => $status === 'present',
                    'status' => $status,
                ],
            );
        }

        return back()->with('success', 'Attendance saved.');
    }

    /**
     * @return array<string, mixed>
     */
    private function rollPayload(Meeting $meeting): array
    {
        $meeting->load('branch', 'attendances');
        $marked = $meeting->attendances->keyBy('member_id');

        $members = Member::query()
            ->with('branch')
            ->where('status', MemberStatus::Active)
            ->when($meeting->branch_id, fn ($q) => $q->where('branch_id', $meeting->branch_id))
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get();

        $rows = $members->map(function (Member $member) use ($marked) {
            $row = $marked[$member->id] ?? null;
            $status = $row?->status
                ?: (($row?->present ?? false) ? 'present' : 'absent');

            return [
                'uuid' => $member->uuid,
                'number' => $member->membership_number,
                'name' => $member->fullName(),
                'branch' => $member->branch?->name,
                'status' => $status,
            ];
        });

        $present = $rows->where('status', 'present')->count();
        $permitted = $rows->where('status', 'permitted')->count();
        $total = $rows->count();

        return [
            'meeting' => [
                'uuid' => $meeting->uuid,
                'title' => $meeting->title,
                'held_on' => $meeting->held_on?->format('d M Y'),
                'kind_label' => $this->kindLabel($meeting->kind),
                'branch' => $meeting->branch?->name,
                'notes' => $meeting->notes,
            ],
            'members' => $rows->values()->all(),
            'stats' => [
                'total' => $total,
                'present' => $present,
                'permitted' => $permitted,
                'absent' => max(0, $total - $present - $permitted),
                'rate' => $total > 0 ? (int) round(($present / $total) * 100) : 0,
            ],
        ];
    }

    private function kindLabel(?string $kind): string
    {
        return match ($kind) {
            'agm' => 'AGM',
            'committee' => 'Committee',
            'general' => 'General meeting',
            default => $kind ? str_replace('_', ' ', ucfirst($kind)) : 'Meeting',
        };
    }
}
