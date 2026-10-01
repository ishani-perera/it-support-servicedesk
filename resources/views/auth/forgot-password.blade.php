@extends('layouts.guest')

@section('title', 'Forgot password')

@section('content')
    <p class="mb-4 text-sm text-slate-600">Enter your work email and we will send you a link to choose a new password.</p>

    <form method="POST" action="{{ route('password.email') }}" class="space-y-4">
        @csrf

        <div>
            <label for="email" class="block text-sm font-medium">Email</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username"
                   class="mt-1 block w-full rounded border border-slate-300 px-3 py-2 text-sm">
            @error('email') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <button type="submit" class="w-full rounded bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">
            Email reset link
        </button>

        <p class="text-center text-sm"><a href="{{ route('login') }}" class="text-slate-600 underline">Back to sign in</a></p>
    </form>
@endsection
