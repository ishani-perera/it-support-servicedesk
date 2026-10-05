@props(['priority'])
@php
    $level = \App\Enums\TicketPriorityLevel::tryFrom((int) $priority->level);
    $classes = match ($level) {
        \App\Enums\TicketPriorityLevel::Low => 'border-slate-200 bg-slate-100 text-slate-700',
        \App\Enums\TicketPriorityLevel::Medium => 'border-sky-200 bg-sky-50 text-sky-800',
        \App\Enums\TicketPriorityLevel::High => 'border-orange-200 bg-orange-50 text-orange-900',
        \App\Enums\TicketPriorityLevel::Critical => 'border-rose-200 bg-rose-50 text-rose-900 shadow-[0_1px_5px_rgb(225_29_72/0.1)]',
        default => 'border-slate-200 bg-slate-100 text-slate-700',
    };
@endphp
<span {{ $attributes->class(['inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-[11px] font-bold leading-none', $classes]) }}><span class="size-1.5 rounded-full bg-current opacity-75" aria-hidden="true"></span>{{ $priority->name }}</span>
