@props(['status'])
@php
    $slug = \App\Enums\TicketStatusSlug::tryFrom($status->slug);
    $classes = match ($slug) {
        \App\Enums\TicketStatusSlug::Open => 'bg-sky-50 text-sky-700 ring-sky-600/15',
        \App\Enums\TicketStatusSlug::Assigned => 'bg-violet-50 text-violet-700 ring-violet-600/15',
        \App\Enums\TicketStatusSlug::InProgress => 'bg-amber-50 text-amber-800 ring-amber-600/15',
        \App\Enums\TicketStatusSlug::WaitingForUser => 'bg-orange-50 text-orange-800 ring-orange-600/15',
        \App\Enums\TicketStatusSlug::Resolved => 'bg-emerald-50 text-emerald-700 ring-emerald-600/15',
        \App\Enums\TicketStatusSlug::Closed => 'bg-slate-100 text-slate-700 ring-slate-500/15',
        default => 'bg-slate-100 text-slate-700 ring-slate-500/15',
    };
@endphp
<span {{ $attributes->class(['inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset', $classes]) }}>{{ $status->name }}</span>
