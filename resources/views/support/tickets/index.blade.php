@extends('layouts.app')

@section('title', 'Support ticket board')
@section('topline', 'Queue and assigned workload')

@section('content')
    <section class="mb-6 flex flex-col justify-between gap-4 rounded-2xl border border-white/70 bg-white/65 p-5 shadow-sm backdrop-blur sm:flex-row sm:items-center sm:px-6">
        <div><p class="ui-eyebrow">Ticket management</p><h1 class="mt-1 text-3xl font-bold tracking-tight text-slate-950">Support queue</h1><p class="mt-2 text-sm text-slate-500">Your authorized tickets, capped at 20 items per board column.</p></div>
        <div class="inline-flex w-fit rounded-xl border border-slate-200 bg-slate-100/80 p-1"><a href="{{ route('tickets.index', array_merge(request()->query(), ['view' => 'board'])) }}" @class(['rounded-lg px-4 py-2 text-sm font-semibold transition', 'bg-white text-violet-800 shadow-sm ring-1 ring-slate-200/80' => $mode === 'board', 'text-slate-500 hover:text-slate-800' => $mode !== 'board'])>Kanban</a><a href="{{ route('tickets.index', array_merge(request()->query(), ['view' => 'table'])) }}" @class(['rounded-lg px-4 py-2 text-sm font-semibold transition', 'bg-white text-violet-800 shadow-sm ring-1 ring-slate-200/80' => $mode === 'table', 'text-slate-500 hover:text-slate-800' => $mode !== 'table'])>Table</a></div>
    </section>

    <section class="mb-6 ui-card p-4 shadow-sm sm:p-5" aria-label="Search and filter tickets">
        <form method="GET" action="{{ route('tickets.index') }}" class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-8">
            <input type="hidden" name="view" value="{{ $mode }}">
            <label class="sm:col-span-2"><span class="mb-1.5 block text-xs font-semibold text-slate-600">Search</span><input type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Number, title, requester" class="w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"></label>
            <label><span class="mb-1.5 block text-xs font-semibold text-slate-600">Status</span><select name="status" class="w-full rounded-lg border-slate-300 text-sm"><option value="">All statuses</option>@foreach ($statuses as $status)<option value="{{ $status->slug }}" @selected(($filters['status'] ?? '') === $status->slug)>{{ $status->name }}</option>@endforeach</select></label>
            <label><span class="mb-1.5 block text-xs font-semibold text-slate-600">Priority</span><select name="priority_id" class="w-full rounded-lg border-slate-300 text-sm"><option value="">All priorities</option>@foreach ($priorities as $priority)<option value="{{ $priority->id }}" @selected((string) ($filters['priority_id'] ?? '') === (string) $priority->id)>{{ $priority->name }}</option>@endforeach</select></label>
            <label><span class="mb-1.5 block text-xs font-semibold text-slate-600">Category</span><select name="category_id" class="w-full rounded-lg border-slate-300 text-sm"><option value="">All categories</option>@foreach ($categories as $category)<option value="{{ $category->id }}" @selected((string) ($filters['category_id'] ?? '') === (string) $category->id)>{{ $category->name }}</option>@endforeach</select></label>
            <label><span class="mb-1.5 block text-xs font-semibold text-slate-600">Assignment</span><select name="assignment" class="w-full rounded-lg border-slate-300 text-sm"><option value="">All visible</option><option value="mine" @selected(($filters['assignment'] ?? '') === 'mine')>Assigned to me</option><option value="unassigned" @selected(($filters['assignment'] ?? '') === 'unassigned')>Unassigned</option></select></label>
            <label><span class="mb-1.5 block text-xs font-semibold text-slate-600">Assigned support user</span><select name="assigned_to" class="w-full rounded-lg border-slate-300 text-sm"><option value="">Any assignee</option>@foreach ($assignees as $assignee)<option value="{{ $assignee->id }}" @selected((string) ($filters['assigned_to'] ?? '') === (string) $assignee->id)>{{ $assignee->name }}</option>@endforeach</select></label>
            <label><span class="mb-1.5 block text-xs font-semibold text-slate-600">Sort</span><select name="sort" class="w-full rounded-lg border-slate-300 text-sm"><option value="newest" @selected(($filters['sort'] ?? 'newest') === 'newest')>Newest</option><option value="oldest" @selected(($filters['sort'] ?? '') === 'oldest')>Oldest</option><option value="updated" @selected(($filters['sort'] ?? '') === 'updated')>Recently updated</option><option value="priority" @selected(($filters['sort'] ?? '') === 'priority')>Highest priority</option></select></label>
            <div class="flex items-end gap-2 sm:col-span-2 xl:col-span-8"><button type="submit" class="rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-slate-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900">Apply filters</button><a href="{{ route('tickets.index', ['view' => $mode]) }}" class="ui-button-secondary">Clear</a><span class="ml-auto pb-2 text-xs text-slate-500">{{ $tickets->total() }} matching tickets</span></div>
        </form>
    </section>

    @if ($mode === 'board')
        <section class="-mx-4 overflow-x-auto px-4 pb-3 sm:-mx-8 sm:px-8" aria-label="Ticket Kanban board">
            <div class="flex min-h-96 w-max items-start gap-4">
                @foreach ($statuses as $status)
                    @php($column = $board[$status->slug] ?? collect())
                    <section class="w-[19rem] shrink-0 overflow-hidden rounded-2xl border border-slate-200 bg-[#eef0f7] shadow-sm" style="border-top: 3px solid {{ $status->color }}" aria-label="{{ $status->name }} tickets">
                        <header class="flex items-center justify-between border-b border-slate-200/80 bg-gradient-to-r from-white/90 to-indigo-50/80 px-4 py-3.5"><div class="flex items-center gap-2"><x-ticket-status-badge :status="$status" /><span class="grid min-w-7 place-items-center rounded-lg bg-white px-2 py-1 text-[11px] font-bold tabular-nums text-slate-600 shadow-sm ring-1 ring-slate-200/70">{{ $column->count() }}{{ $column->count() === 20 ? '+' : '' }}</span></div><span class="text-[10px] font-bold uppercase tracking-widest text-slate-400">queue</span></header>
                        <div class="max-h-[72vh] space-y-3 overflow-y-auto p-3">
                            @forelse ($column as $ticket)
                                <article class="ui-card-hover rounded-xl border border-slate-200/80 bg-white p-4 shadow-[0_2px_8px_rgb(25_28_55/0.05)]">
                                    <div class="flex items-start justify-between gap-2"><a href="{{ route('tickets.show', $ticket) }}" class="text-xs font-bold text-indigo-700 hover:text-indigo-900">{{ $ticket->ticket_number }}</a><x-ticket-priority-badge :priority="$ticket->priority" /></div>
                                    <a href="{{ route('tickets.show', $ticket) }}" class="mt-2 block text-sm font-semibold text-slate-900 hover:text-indigo-700">{{ $ticket->title }}</a>
                                    <p class="mt-2 text-xs text-slate-500">{{ $ticket->user->name }} · {{ $ticket->category->name }}</p>
                                    <p class="mt-1 truncate text-xs text-slate-500">{{ $ticket->currentAssignment?->assignee?->name ?? 'Unassigned' }}</p>
                                    <p class="mt-2 text-xs text-slate-400">Updated {{ $ticket->updated_at->diffForHumans() }}</p>
                                    <div class="mt-3 space-y-2 border-t border-slate-100 pt-3">
                                        @can('updateStatus', $ticket)
                                            <form method="POST" action="{{ route('tickets.status', $ticket) }}" class="space-y-2" data-resolution-form>@csrf @method('PATCH')<input type="hidden" name="_html_form" value="1"><div class="flex gap-2"><label class="sr-only" for="status-{{ $ticket->id }}">Change status for {{ $ticket->ticket_number }}</label><select id="status-{{ $ticket->id }}" name="status" required class="min-w-0 flex-1 rounded-lg border-slate-300 text-xs" data-resolution-status><option value="">Choose next status</option>@foreach ($transitionOptions[$ticket->id] ?? [] as $option)<option value="{{ $option->value }}">{{ $option->label() }}</option>@endforeach</select><button class="rounded-lg bg-slate-900 px-3 py-2 text-xs font-semibold text-white">Update</button></div><div data-resolution-field class="hidden"><label class="mb-1 block text-xs font-semibold text-slate-600" for="resolution-{{ $ticket->id }}">Solution <span class="text-rose-600">*</span></label><textarea id="resolution-{{ $ticket->id }}" name="resolution" rows="3" maxlength="20000" data-resolution-input class="w-full rounded-lg border-slate-300 text-xs" placeholder="Describe the fix">{{ old('resolution', $ticket->resolution) }}</textarea></div></form>
                                        @endcan
                                        @can('create', [\App\Models\TicketAssignment::class, $ticket])
                                            @if ($ticket->currentAssignment && $ticket->currentAssignment->assigned_to === auth()->id())
                                                <form method="POST" action="{{ route('tickets.assignments.store', $ticket) }}" class="flex gap-2">@csrf<label class="sr-only" for="assignee-{{ $ticket->id }}">Reassign {{ $ticket->ticket_number }}</label><select id="assignee-{{ $ticket->id }}" name="assigned_to" class="min-w-0 flex-1 rounded-lg border-slate-300 text-xs">@foreach ($assignees as $assignee)<option value="{{ $assignee->id }}" @selected($assignee->id === $ticket->currentAssignment->assigned_to)>{{ $assignee->name }}</option>@endforeach</select><input type="hidden" name="_html_form" value="1"><button class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700">Reassign</button></form>
                                                <form method="POST" action="{{ route('tickets.assignments.destroy', $ticket) }}">@csrf @method('DELETE')<input type="hidden" name="_html_form" value="1"><button class="text-xs font-semibold text-slate-500 hover:text-rose-700">Return to unassigned</button></form>
                                            @elseif (! $ticket->currentAssignment)
                                                <form method="POST" action="{{ route('tickets.assignments.store', $ticket) }}">@csrf<input type="hidden" name="assigned_to" value="{{ auth()->id() }}"><input type="hidden" name="_html_form" value="1"><button class="w-full rounded-lg border border-indigo-200 bg-indigo-50 px-3 py-2 text-xs font-semibold text-indigo-700">Assign to me</button></form>
                                            @endif
                                        @endcan
                                    </div>
                                </article>
                            @empty
                                <p class="rounded-xl border border-dashed border-slate-300 px-3 py-8 text-center text-xs text-slate-500">No tickets in this status.</p>
                            @endforelse
                        </div>
                    </section>
                @endforeach
            </div>
        </section>
    @else
        <section class="ui-card overflow-hidden">
            @if ($tickets->isEmpty())<p class="px-6 py-14 text-center text-sm text-slate-500">No tickets match these filters.</p>
            @else
                <div class="overflow-x-auto"><table class="w-full min-w-[900px] text-left text-sm"><thead class="ui-table-head"><tr><th class="px-5 py-3">Ticket / requester</th><th class="px-4 py-3">Category</th><th class="px-4 py-3">Priority</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Assignee</th><th class="px-4 py-3">Updated</th></tr></thead><tbody class="divide-y divide-slate-100">@foreach ($tickets as $ticket)<tr class="hover:bg-slate-50"><td class="px-5 py-4"><a href="{{ route('tickets.show', $ticket) }}" class="font-semibold text-slate-900 hover:text-indigo-700">{{ $ticket->ticket_number }} · {{ $ticket->title }}</a><span class="mt-1 block text-xs text-slate-500">{{ $ticket->user->name }}</span></td><td class="px-4 py-4">{{ $ticket->category->name }}</td><td class="px-4 py-4"><x-ticket-priority-badge :priority="$ticket->priority" /></td><td class="px-4 py-4"><x-ticket-status-badge :status="$ticket->status" /></td><td class="px-4 py-4">{{ $ticket->currentAssignment?->assignee?->name ?? 'Unassigned' }}</td><td class="px-4 py-4 whitespace-nowrap text-slate-500">{{ $ticket->updated_at->diffForHumans() }}</td></tr>@endforeach</tbody></table></div>
                <div class="border-t border-slate-100 px-4 py-4 sm:px-6">{{ $tickets->appends(['view' => 'table'])->links() }}</div>
            @endif
        </section>
    @endif
@endsection
