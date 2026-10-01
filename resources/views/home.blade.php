@extends('layouts.guest')

@section('title', 'Signed in')

@section('content')
    {{-- Placeholder landing page: proves authentication works. Dashboards come in later phases. --}}
    <p class="text-sm text-slate-500">Signed in as</p>
    <p class="font-medium">{{ auth()->user()->name }}</p>
    <p class="text-sm text-slate-600">{{ auth()->user()->email }} · {{ auth()->user()->role->label() }}</p>

    {{-- Blade @can/@cannot only HIDE UI. The server still authorizes every request (see the policies). --}}
    @can('viewAny', \App\Models\User::class)
        <p class="mt-4 rounded bg-slate-100 p-3 text-sm text-slate-700" data-testid="admin-hint">Administrator tools are available to you.</p>
    @endcan
    @cannot('viewAny', \App\Models\User::class)
        <p class="mt-4 text-sm text-slate-500" data-testid="standard-hint">Standard access.</p>
    @endcannot

    <form method="POST" action="{{ route('logout') }}" class="mt-6">
        @csrf
        <button type="submit" class="w-full rounded border border-slate-300 px-4 py-2 text-sm font-medium hover:bg-slate-50">Sign out</button>
    </form>
@endsection
