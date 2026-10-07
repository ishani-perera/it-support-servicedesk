@extends('layouts.app')

@section('title', 'Technician dashboard')
@section('topline', 'Assigned work')

@section('content')
    <section class="relative mb-7 overflow-hidden rounded-3xl border border-indigo-100 bg-gradient-to-br from-[#202248] via-indigo-950 to-violet-950 px-5 py-7 text-white shadow-xl shadow-indigo-950/10 sm:px-8 sm:py-9">
        <div class="absolute -right-12 -top-20 size-64 rounded-full bg-violet-500/20 blur-3xl" aria-hidden="true"></div>
        <div class="relative flex flex-col justify-between gap-6 sm:flex-row sm:items-end">
            <div class="max-w-2xl"><p class="text-[11px] font-bold uppercase tracking-[.2em] text-indigo-200">Technician workspace</p><h1 class="mt-2 text-3xl font-bold tracking-tight sm:text-4xl">Your work, all in one place.</h1><p class="mt-3 max-w-xl text-sm leading-6 text-indigo-100/75">Review assigned requests, keep employees updated, and submit your technical work for IT Support review.</p></div>
            <a href="{{ route('technician.tickets.index') }}" class="inline-flex min-h-11 items-center justify-center gap-2 self-start rounded-xl bg-white px-4 py-2.5 text-sm font-bold text-indigo-950 shadow-lg transition hover:-translate-y-0.5 hover:bg-indigo-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white sm:self-auto">View assigned tickets <span aria-hidden="true">→</span></a>
        </div>
    </section>

    <section class="mb-7 grid gap-3 sm:grid-cols-2 xl:grid-cols-4" aria-label="Assigned work summary">
        @foreach ($stats as $stat)
            @php($accent = ['indigo' => ['gradient' => 'from-indigo-500 to-violet-600', 'count' => 'bg-indigo-50 text-indigo-700 ring-indigo-100'], 'violet' => ['gradient' => 'from-violet-500 to-fuchsia-600', 'count' => 'bg-violet-50 text-violet-700 ring-violet-100'], 'blue' => ['gradient' => 'from-blue-500 to-cyan-600', 'count' => 'bg-blue-50 text-blue-700 ring-blue-100'], 'amber' => ['gradient' => 'from-amber-400 to-orange-500', 'count' => 'bg-amber-50 text-amber-700 ring-amber-100'], 'rose' => ['gradient' => 'from-rose-500 to-pink-600', 'count' => 'bg-rose-50 text-rose-700 ring-rose-100'], 'cyan' => ['gradient' => 'from-cyan-500 to-sky-600', 'count' => 'bg-cyan-50 text-cyan-700 ring-cyan-100'], 'emerald' => ['gradient' => 'from-emerald-500 to-teal-600', 'count' => 'bg-emerald-50 text-emerald-700 ring-emerald-100']][$stat['accent']])
            <a href="{{ route('technician.tickets.index', ['status' => $stat['status']]) }}" class="group rounded-2xl border border-slate-200/80 bg-white p-4 shadow-sm transition duration-200 hover:-translate-y-0.5 hover:border-indigo-200 hover:shadow-lg hover:shadow-indigo-950/5 focus-visible:outline focus-visible:outline-2 focus-visible:outline-indigo-600 sm:p-5">
                <div class="flex items-start justify-between gap-3"><span class="grid size-10 place-items-center rounded-xl bg-gradient-to-br {{ $accent['gradient'] }} text-white shadow-sm"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="size-5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01" /></svg></span><span class="rounded-full px-2.5 py-1 text-xs font-bold ring-1 ring-inset {{ $accent['count'] }}">{{ $stat['count'] }}</span></div>
                <p class="mt-4 text-sm font-semibold text-slate-600">{{ $stat['label'] }}</p><p class="mt-1 text-xs font-medium text-indigo-700 transition group-hover:translate-x-0.5">Open queue <span aria-hidden="true">→</span></p>
            </a>
        @endforeach
    </section>

    @include('technician.partials.ticket-list', ['heading' => 'Recently updated assignments', 'description' => 'The latest tickets currently assigned to you.', 'showFilter' => false])
@endsection
