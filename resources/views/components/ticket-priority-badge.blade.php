@props(['priority'])
@php
    $level = \App\Enums\TicketPriorityLevel::tryFrom((int) $priority->level);
    $classes = match ($level) {
        \App\Enums\TicketPriorityLevel::Low => 'bg-slate-100 text-slate-700',
        \App\Enums\TicketPriorityLevel::Medium => 'bg-blue-50 text-blue-700',
        \App\Enums\TicketPriorityLevel::High => 'bg-orange-50 text-orange-800',
        \App\Enums\TicketPriorityLevel::Critical => 'bg-rose-50 text-rose-700',
        default => 'bg-slate-100 text-slate-700',
    };
@endphp
<span {{ $attributes->class(['inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold', $classes]) }}>{{ $priority->name }}</span>
