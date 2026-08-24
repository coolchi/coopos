<?php

namespace App\Http\Controllers\Tenant;

use App\Domain\Notifications\Notifier;
use App\Http\Controllers\Controller;
use App\Models\SupportMessage;
use App\Models\SupportTicket;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class SupportController extends Controller
{
    public function index()
    {
        $tickets = SupportTicket::query()
            ->where('tenant_id', tenant()->id)
            ->with('messages')
            ->withCount('messages')
            ->latest()
            ->get();

        return Inertia::render('Tenant/Support/Index', [
            'counts' => [
                'all' => $tickets->count(),
                'open' => $tickets->whereIn('status', ['open', 'pending'])->count(),
                'resolved' => $tickets->whereIn('status', ['resolved', 'closed'])->count(),
            ],
            'tickets' => $tickets->map(fn (SupportTicket $t) => [
                'uuid' => $t->uuid,
                'subject' => $t->subject,
                'category' => $t->category,
                'priority' => $t->priority,
                'status' => $t->status,
                'messages_count' => $t->messages_count,
                'preview' => $t->messages->sortByDesc('created_at')->first()?->body,
                'created_at' => $t->created_at?->toDayDateTimeString(),
                'updated_at' => $t->updated_at?->diffForHumans(),
            ]),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:160'],
            'category' => ['required', Rule::in(['general', 'technical', 'payment', 'account', 'billing'])],
            'priority' => ['required', Rule::in(['low', 'normal', 'high'])],
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $ticket = SupportTicket::query()->create([
            'tenant_id' => tenant()->id,
            'user_id' => $request->user()->id,
            'category' => $data['category'],
            'priority' => $data['priority'],
            'subject' => $data['subject'],
            'status' => 'open',
        ]);
        $ticket->messages()->create([
            'user_id' => $request->user()->id,
            'body' => $data['body'],
        ]);

        app(Notifier::class)->supportTicketOpened($ticket);

        return redirect()->route('tenant.support.show', [tenant()->slug, $ticket])
            ->with('success', 'Support ticket opened. The CoopOS team will reply here.');
    }

    public function show(string $tenant, SupportTicket $ticket)
    {
        $this->authorizeOwned($ticket);
        $ticket->load(['user', 'messages.user']);

        return Inertia::render('Tenant/Support/Show', [
            'ticket' => [
                'uuid' => $ticket->uuid,
                'subject' => $ticket->subject,
                'category' => $ticket->category,
                'priority' => $ticket->priority,
                'status' => $ticket->status,
                'requester' => $ticket->user?->name,
                'created_at' => $ticket->created_at?->toDayDateTimeString(),
                'resolved_at' => $ticket->resolved_at?->toDayDateTimeString(),
                'is_open' => in_array($ticket->status, ['open', 'pending'], true),
                'messages' => $ticket->messages->sortBy('created_at')->values()->map(fn (SupportMessage $message) => [
                    'body' => $message->body,
                    'author' => $message->user?->name,
                    'is_platform' => (bool) $message->user?->isPlatformAdmin(),
                    'created_at' => $message->created_at?->toDayDateTimeString(),
                ]),
            ],
        ]);
    }

    public function reply(Request $request, string $tenant, SupportTicket $ticket)
    {
        $this->authorizeOwned($ticket);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $ticket->messages()->create([
            'user_id' => $request->user()->id,
            'body' => $data['body'],
        ]);

        $ticket->update([
            'status' => 'open',
            'resolved_at' => null,
        ]);

        app(Notifier::class)->supportTicketReplied($ticket, $request->user());

        return back()->with('success', 'Reply sent.');
    }

    public function close(string $tenant, SupportTicket $ticket)
    {
        $this->authorizeOwned($ticket);

        $ticket->update([
            'status' => 'closed',
            'resolved_at' => now(),
        ]);

        return back()->with('success', 'Ticket closed.');
    }

    private function authorizeOwned(SupportTicket $ticket): void
    {
        abort_unless($ticket->tenant_id === tenant()->id, 404);
    }
}
