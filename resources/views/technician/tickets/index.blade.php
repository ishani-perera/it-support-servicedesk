@extends('layouts.app')

@section('title', 'Assigned tickets')
@section('topline', 'Assigned tickets')

@section('content')
    <section class="ui-page-toolbar mb-6"><p class="ui-eyebrow">Technician queue</p><h1 class="ui-page-title text-3xl">Assigned tickets</h1><p class="mt-2 text-sm text-slate-500">Only tickets currently assigned to your account appear here.</p></section>
    @include('technician.partials.ticket-list', ['heading' => 'Your assigned work', 'description' => 'Find and open a ticket to review its conversation and work report.', 'showFilter' => true])
@endsection
