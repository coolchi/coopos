<?php

namespace App\Http\Controllers\Tenant;

use App\Domain\Notifications\Notifier;
use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\Branch;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class CommunicationController extends Controller
{
    public function index()
    {
        $tenantId = tenant()->id;

        return Inertia::render('Tenant/Communication/Index', [
            'branches' => Branch::query()->orderByDesc('is_headquarters')->orderBy('name')->get(['uuid', 'name', 'code']),
            'platform' => Announcement::query()
                ->with('author')
                ->where('is_platform', true)
                ->whereNotNull('published_at')
                ->latest('published_at')
                ->limit(5)
                ->get()
                ->map(fn (Announcement $a) => $this->payload($a)),
            'announcements' => Announcement::query()
                ->with(['author', 'branch'])
                ->where('tenant_id', $tenantId)
                ->latest()
                ->get()
                ->map(fn (Announcement $a) => $this->payload($a)),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $announcement = Announcement::query()->create([
            ...$this->attributes($data),
            'tenant_id' => tenant()->id,
            'created_by' => $request->user()->id,
        ]);

        if ($announcement->published_at) {
            app(Notifier::class)->announcementPublished($announcement);
        }

        return back()->with('success', $request->boolean('publish') ? 'Announcement published.' : 'Draft saved.');
    }

    public function update(Request $request, string $tenant, Announcement $announcement)
    {
        $this->authorizeOwned($announcement);
        $wasDraft = $announcement->published_at === null;
        $data = $this->validated($request);
        $announcement->update($this->attributes($data, $announcement));

        if ($wasDraft && $announcement->fresh()->published_at) {
            app(Notifier::class)->announcementPublished($announcement->fresh());
        }

        return back()->with('success', 'Announcement updated.');
    }

    public function publish(string $tenant, Announcement $announcement)
    {
        $this->authorizeOwned($announcement);
        $announcement->update(['published_at' => now()]);
        app(Notifier::class)->announcementPublished($announcement->fresh());

        return back()->with('success', 'Announcement published.');
    }

    public function pin(string $tenant, Announcement $announcement)
    {
        $this->authorizeOwned($announcement);
        $announcement->update(['is_pinned' => ! $announcement->is_pinned]);

        return back()->with('success', $announcement->is_pinned ? 'Announcement pinned.' : 'Announcement unpinned.');
    }

    public function destroy(string $tenant, Announcement $announcement)
    {
        $this->authorizeOwned($announcement);
        $announcement->delete();

        return back()->with('success', 'Announcement removed.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'body' => ['required', 'string', 'max:5000'],
            'audience' => ['required', Rule::in(['all', 'active', 'branch'])],
            'branch_uuid' => ['nullable', 'required_if:audience,branch', 'uuid'],
            'is_pinned' => ['sometimes', 'boolean'],
            'publish' => ['sometimes', 'boolean'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data, ?Announcement $existing = null): array
    {
        $branchId = null;
        if (($data['audience'] ?? 'all') === 'branch' && ! empty($data['branch_uuid'])) {
            $branchId = Branch::query()->where('uuid', $data['branch_uuid'])->value('id');
        }

        $publish = request()->boolean('publish');
        $publishedAt = $existing?->published_at;
        if ($publish) {
            $publishedAt = $publishedAt ?? now();
        } elseif ($existing === null && ! $publish) {
            $publishedAt = null;
        }

        return [
            'title' => $data['title'],
            'body' => $data['body'],
            'audience' => $data['audience'],
            'branch_id' => $branchId,
            'is_pinned' => array_key_exists('is_pinned', $data)
                ? (bool) $data['is_pinned']
                : (bool) ($existing?->is_pinned ?? false),
            'published_at' => $publishedAt,
        ];
    }

    private function authorizeOwned(Announcement $announcement): void
    {
        abort_if($announcement->is_platform || $announcement->tenant_id !== tenant()->id, 404);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Announcement $a): array
    {
        return [
            'uuid' => $a->uuid,
            'title' => $a->title,
            'body' => $a->body,
            'audience' => $a->audience ?: 'all',
            'branch' => $a->branch?->name,
            'branch_uuid' => $a->branch?->uuid,
            'is_pinned' => $a->is_pinned,
            'is_platform' => $a->is_platform,
            'is_draft' => $a->published_at === null,
            'author' => $a->author?->name,
            'published_at' => $a->published_at?->toDayDateTimeString(),
            'created_at' => $a->created_at?->toDayDateTimeString(),
        ];
    }
}
