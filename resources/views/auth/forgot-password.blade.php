@extends('layouts.guest')

@section('title', 'Forgot password')

@section('content')
    <p class="mb-4 text-sm text-slate-600">Enter your work email and we will send you a link to choose a new password.</p>

    <form method="POST" action="{{ route('password.email') }}" class="space-y-4">
        @csrf

        <div>
            <label for="email" class="ui-field-label">Work email</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username"
                   class="block w-full border px-3 py-2.5 text-sm">
            @error('email') <p class="mt-1.5 text-sm font-medium text-rose-700">{{ $message }}</p> @enderror
        </div>

        <button type="submit" class="ui-button-primary w-full py-3">Email reset link</button>

        <p class="text-center text-sm"><a href="{{ route('login') }}" class="font-semibold text-indigo-700 underline decoration-indigo-200 underline-offset-4 hover:text-indigo-900">Back to sign in</a></p>
    </form>
@endsection
