<?php

namespace App\Http\Controllers\Platform;

use App\Domain\Notifications\Notifier;
use App\Http\Controllers\Controller;
use App\Models\SupportMessage;
use App\Models\SupportTicket;
use App\Support\TenantContext;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TicketController extends Controller
{
    public function index(Request $request)
    {
        TenantContext::bypass();

        $tickets = SupportTicket::query()
            ->with('tenant')
            ->when($request->string('status')->toString(), fn ($q, $status) => $q->where('status', $status))
            ->when($request->string('priority')->toString(), fn ($q, $priority) => $q->where('priority', $priority))
            ->latest()
            ->paginate(20)
            ->withQueryString()
            ->through(fn (SupportTicket $ticket) => [
                'uuid' => $ticket->uuid,
                'subject' => $ticket->subject,
                'category' => $ticket->category,
                'priority' => $ticket->priority,
                'status' => $ticket->status,
                'cooperative' => $ticket->tenant?->name,
                'slug' => $ticket->tenant?->slug,
                'created_at' => $ticket->created_at?->diffForHumans(),
            ]);

        return Inertia::render('Platform/Tickets/Index', [
            'tickets' => $tickets,
            'filters' => $request->only(['status', 'priority']),
        ]);
    }

    public function show(SupportTicket $ticket)
    {
        TenantContext::bypass();
        $ticket->load(['tenant', 'user', 'messages.user']);

        return Inertia::render('Platform/Tickets/Show', [
            'ticket' => [
                'uuid' => $ticket->uuid,
                'subject' => $ticket->subject,
                'category' => $ticket->category,
                'priority' => $ticket->priority,
                'status' => $ticket->status,
                'cooperative' => $ticket->tenant?->name,
                'slug' => $ticket->tenant?->slug,
                'requester' => $ticket->user?->name,
                'created_at' => $ticket->created_at?->toDayDateTimeString(),
                'messages' => $ticket->messages->map(fn (SupportMessage $message) => [
                    'body' => $message->body,
                    'author' => $message->user?->name,
                    'created_at' => $message->created_at?->diffForHumans(),
                ]),
            ],
        ]);
    }

    public function reply(Request $request, SupportTicket $ticket)
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
            'status' => ['nullable', 'in:open,pending,resolved,closed'],
        ]);

        $ticket->messages()->create([
            'user_id' => $request->user()->id,
            'body' => $data['body'],
        ]);

        $ticket->update([
            'status' => $data['status'] ?? 'pending',
            'assigned_to' => $request->user()->id,
            'resolved_at' => in_array($data['status'] ?? 'pending', ['resolved', 'closed'], true) ? now() : null,
        ]);

        app(Notifier::class)->supportTicketReplied($ticket, $request->user());

        return back()->with('success', 'Reply sent.');
    }
}
