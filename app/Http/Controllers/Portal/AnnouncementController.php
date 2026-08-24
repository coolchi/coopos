<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\Member;
use Inertia\Inertia;
use Inertia\Response;

class AnnouncementController extends Controller
{
    public function index(): Response
    {
        $member = $this->member();

        return Inertia::render('Portal/Announcements', [
            'announcements' => Announcement::query()
                ->forPortal($member)
                ->get()
                ->map(fn (Announcement $a) => $this->card($a))
                ->values(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function card(Announcement $a): array
    {
        return [
            'uuid' => $a->uuid,
            'title' => $a->title,
            'body' => $a->body,
            'is_pinned' => $a->is_pinned,
            'is_platform' => $a->is_platform,
            'published_at' => $a->published_at?->toFormattedDateString(),
        ];
    }

    private function member(): Member
    {
        return request()->user()->member()->firstOrFail();
    }
}
