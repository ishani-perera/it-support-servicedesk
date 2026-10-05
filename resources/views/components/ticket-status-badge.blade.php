@props(['status'])
@php
    $slug = \App\Enums\TicketStatusSlug::tryFrom($status->slug);
    $classes = match ($slug) {
        \App\Enums\TicketStatusSlug::Open => 'border-sky-200 bg-sky-50 text-sky-800 ring-sky-600/10',
        \App\Enums\TicketStatusSlug::Assigned => 'border-violet-200 bg-violet-50 text-violet-800 ring-violet-600/10',
        \App\Enums\TicketStatusSlug::InProgress => 'border-indigo-200 bg-indigo-50 text-indigo-800 ring-indigo-600/10',
        \App\Enums\TicketStatusSlug::WaitingForUser => 'border-amber-200 bg-amber-50 text-amber-900 ring-amber-600/10',
        \App\Enums\TicketStatusSlug::Resolved => 'border-emerald-200 bg-emerald-50 text-emerald-800 ring-emerald-600/10',
        \App\Enums\TicketStatusSlug::Closed => 'border-slate-200 bg-slate-100 text-slate-700 ring-slate-500/10',
        default => 'border-slate-200 bg-slate-100 text-slate-700 ring-slate-500/10',
    };
@endphp
<span {{ $attributes->class(['inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-[11px] font-bold leading-none ring-1 ring-inset', $classes]) }}><span class="size-1.5 rounded-full bg-current opacity-70" aria-hidden="true"></span>{{ $status->name }}</span>
