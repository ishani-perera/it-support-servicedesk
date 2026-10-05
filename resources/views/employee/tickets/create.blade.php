@extends('layouts.app')

@section('title', 'Create a ticket')
@section('topline', 'Tell us what’s going on')

@section('content')
    <div class="-mx-4 -my-6 min-h-full bg-slate-50 px-4 py-6 sm:-mx-7 sm:px-7 sm:py-8 xl:-mx-10 xl:px-10">
        <div class="mx-auto max-w-3xl">
            <a href="{{ route('tickets.index') }}" class="mb-5 inline-flex items-center gap-2 rounded-lg text-sm font-medium text-slate-500 transition hover:text-indigo-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-indigo-600"><span aria-hidden="true">←</span> Back to my tickets</a>

            <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <header class="border-b border-slate-100 px-6 py-6 sm:px-8 sm:py-7">
                    <p class="text-sm font-semibold text-indigo-600">New support request</p>
                    <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-900">What do you need help with?</h1>
                    <p class="mt-2 max-w-xl text-sm leading-6 text-slate-500">Share a few details so our IT team can get started. You can follow the conversation from your ticket.</p>
                </header>

                <form method="POST" action="{{ route('tickets.store') }}" enctype="multipart/form-data" class="create-ticket-form space-y-6 p-6 sm:p-8" data-ticket-form data-loading-form>
                    @csrf
                    <input type="hidden" name="_html_form" value="1">
                    @php
                        $titleDescriptionIds = $errors->has('title') ? 'title-help title-error' : 'title-help';
                        $descriptionDescriptionIds = $errors->has('description') ? 'description-help description-error' : 'description-help';
                        $fileDescriptionIds = $errors->has('file') ? 'file-help file-error' : 'file-help';
                    @endphp

                    <div>
                        <label for="title" class="mb-1.5 block text-sm font-semibold text-slate-700">Title <span class="text-rose-500">*</span></label>
                        <input id="title" name="title" type="text" required maxlength="255" value="{{ old('title') }}" placeholder="A short summary of the issue" class="w-full rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-900 shadow-sm transition-all placeholder:text-slate-400 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 focus:outline-none @error('title') border-rose-400 @enderror" aria-describedby="{{ $titleDescriptionIds }}" @if ($errors->has('title')) aria-invalid="true" @endif>
                        <p id="title-help" class="mt-1.5 text-xs text-slate-500">Keep it clear and specific.</p>
                        @error('title')<p id="title-error" class="mt-1.5 text-sm font-medium text-rose-700">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="description" class="mb-1.5 block text-sm font-semibold text-slate-700">Description <span class="text-rose-500">*</span></label>
                        <textarea id="description" name="description" rows="7" required maxlength="20000" placeholder="What happened? What were you trying to do? Include any error message that might help." class="w-full resize-y rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm leading-6 text-slate-900 shadow-sm transition-all placeholder:text-slate-400 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 focus:outline-none @error('description') border-rose-400 @enderror" aria-describedby="{{ $descriptionDescriptionIds }}" @if ($errors->has('description')) aria-invalid="true" @endif>{{ old('description') }}</textarea>
                        <p id="description-help" class="mt-1.5 text-xs text-slate-500">Include what you tried and what happened next.</p>
                        @error('description')<p id="description-error" class="mt-1.5 text-sm font-medium text-rose-700">{{ $message }}</p>@enderror
                    </div>

                    <div class="grid gap-5 sm:grid-cols-2">
                        <div>
                            <label for="category_id" class="mb-1.5 block text-sm font-semibold text-slate-700">Category <span class="text-rose-500">*</span></label>
                            <select id="category_id" name="category_id" required class="w-full rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-900 shadow-sm transition-all focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 focus:outline-none @error('category_id') border-rose-400 @enderror" @if ($errors->has('category_id')) aria-describedby="category-error" aria-invalid="true" @endif>
                                <option value="">Choose a category</option>
                                @foreach ($categories as $category)<option value="{{ $category->id }}" @selected((string) old('category_id') === (string) $category->id)>{{ $category->name }}</option>@endforeach
                            </select>
                            @error('category_id')<p id="category-error" class="mt-1.5 text-sm font-medium text-rose-700">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="priority_id" class="mb-1.5 block text-sm font-semibold text-slate-700">Priority <span class="text-rose-500">*</span></label>
                            <select id="priority_id" name="priority_id" required class="w-full rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-900 shadow-sm transition-all focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 focus:outline-none @error('priority_id') border-rose-400 @enderror" @if ($errors->has('priority_id')) aria-describedby="priority-error" aria-invalid="true" @endif>
                                <option value="">Choose a priority</option>
                                @foreach ($priorities as $priority)<option value="{{ $priority->id }}" @selected((string) old('priority_id') === (string) $priority->id)>{{ $priority->name }}</option>@endforeach
                            </select>
                            @error('priority_id')<p id="priority-error" class="mt-1.5 text-sm font-medium text-rose-700">{{ $message }}</p>@enderror
                        </div>
                    </div>

                    <div data-file-picker>
                        <label for="file" class="mb-1.5 block text-sm font-semibold text-slate-700">Attachment <span class="font-normal text-slate-500">(optional)</span></label>
                        <input id="file" name="file" type="file" accept=".pdf,.txt,.png,.jpg,.jpeg,.doc,.docx,.xls,.xlsx" class="peer sr-only" aria-describedby="{{ $fileDescriptionIds }}" @if ($errors->has('file')) aria-invalid="true" @endif>
                        <label for="file" data-file-dropzone class="group flex cursor-pointer flex-col items-center rounded-xl border-2 border-dashed border-slate-300 bg-white p-6 text-center transition-all hover:border-indigo-400 hover:bg-slate-50 peer-focus-visible:border-indigo-500 peer-focus-visible:ring-2 peer-focus-visible:ring-indigo-500/20">
                            <span class="grid size-12 place-items-center rounded-2xl bg-indigo-50 text-indigo-600 transition group-hover:bg-indigo-100"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" class="size-6" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 16V4m0 0L7.5 8.5M12 4l4.5 4.5M5 14.5v3A2.5 2.5 0 0 0 7.5 20h9a2.5 2.5 0 0 0 2.5-2.5v-3"/></svg></span>
                            <span class="mt-3 text-sm font-semibold text-slate-800"><span class="text-indigo-600">Click to upload</span> or drag and drop</span>
                            <span class="mt-1 text-xs text-slate-500">PDF, TXT, images or Office documents · up to 10 MB</span>
                        </label>
                        <p id="file-help" class="sr-only">Select one PDF, text, image, or Office document up to 10 MB.</p>
                        @error('file')<p id="file-error" class="mt-1.5 text-sm font-medium text-rose-700">{{ $message }}</p>@enderror
                        <div data-file-details class="mt-3 hidden items-center justify-between gap-3 rounded-xl border border-indigo-100 bg-indigo-50/50 px-4 py-3"><span data-file-name class="min-w-0 truncate text-sm font-semibold text-slate-700"></span><span data-file-size class="shrink-0 text-xs text-slate-500"></span><button type="button" data-file-remove class="shrink-0 rounded-lg px-2.5 py-1.5 text-xs font-semibold text-rose-700 transition hover:bg-rose-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-rose-600">Remove</button></div>
                    </div>

                    <div class="flex flex-col-reverse gap-3 border-t border-slate-200 pt-5 sm:flex-row sm:justify-end">
                        <a href="{{ route('tickets.index') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white px-5 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-indigo-600">Cancel</a>
                        <button type="submit" data-loading-label="Submitting…" class="inline-flex items-center justify-center gap-2 rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">Submit ticket <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="size-4" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14m-6-6 6 6-6 6"/></svg></button>
                    </div>
                </form>
            </section>
        </div>
    </div>
@endsection
