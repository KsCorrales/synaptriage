<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessTicketTriage;
use App\Models\SupportTicket;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TicketController extends Controller
{
    public function index(): Response
    {
        $tickets = SupportTicket::orderByDesc('created_at')
            ->paginate(50);

        return Inertia::render('Tickets/Index', [
            'tickets' => $tickets,
        ]);
    }

    public function store(Request $request): \Illuminate\Http\RedirectResponse
    {
        $validated = $request->validate([
            'subject'                   => ['required', 'string', 'max:255'],
            'body'                      => ['required', 'string'],
            'category'                  => ['required', 'in:billing,technical,outage,general,account'],
            'customer_tier'             => ['required', 'in:free,starter,professional,enterprise'],
            'response_time_expectation' => ['required', 'integer', 'min:1', 'max:72'],
        ]);

        $ticket = SupportTicket::create($validated);

        ProcessTicketTriage::dispatch($ticket);

        return redirect()->route('tickets.index');
    }
}