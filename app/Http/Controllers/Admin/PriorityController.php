<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdatePriorityRequest;
use App\Models\TicketPriority;
use App\Models\TicketStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PriorityController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', TicketPriority::class);
        $priorities = TicketPriority::query()->withCount('tickets')->orderBy('level')->paginate(25);

        return view('admin.priorities.index', compact('priorities'));
    }

    public function edit(TicketPriority $priority): View
    {
        $this->authorize('update', $priority);

        return view('admin.priorities.edit', compact('priority'));
    }

    public function update(UpdatePriorityRequest $request, TicketPriority $priority): RedirectResponse
    {
        $priority->fill($request->validated())->save();

        return redirect()->route('admin.priorities.index')->with('status', 'Priority settings updated.');
    }

    public function statuses(): View
    {
        $this->authorize('viewAny', TicketStatus::class);
        $statuses = TicketStatus::query()->withCount('tickets')->orderBy('sort_order')->paginate(25);

        return view('admin.statuses.index', compact('statuses'));
    }
}
