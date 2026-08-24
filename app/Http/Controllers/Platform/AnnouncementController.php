<?php

namespace App\Http\Controllers\Platform;

use App\Domain\Notifications\Notifier;
use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Support\TenantContext;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AnnouncementController extends Controller
{
    public function index()
    {
        TenantContext::bypass();

        return Inertia::render('Platform/Announcements', [
            'announcements' => Announcement::query()->latest()->paginate(20)->withQueryString()->through(fn (Announcement $item) => [
                'uuid' => $item->uuid,
                'title' => $item->title,
                'body' => $item->body,
                'is_platform' => $item->is_platform,
                'published_at' => $item->published_at?->toDayDateTimeString(),
            ]),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $announcement = Announcement::query()->create([
            'title' => $data['title'],
            'body' => $data['body'],
            'is_platform' => true,
            'created_by' => $request->user()->id,
            'published_at' => now(),
        ]);

        app(Notifier::class)->announcementPublished($announcement);

        return back()->with('success', 'Announcement published to every cooperative.');
    }

    public function destroy(Announcement $announcement)
    {
        $announcement->delete();

        return back()->with('success', 'Announcement removed.');
    }
}
