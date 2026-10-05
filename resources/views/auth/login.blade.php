@extends('layouts.guest')

@section('title', 'Sign in')

@section('content')
    <form method="POST" action="{{ route('login.store') }}" class="space-y-5">
        @csrf

        <div>
            <label for="email" class="ui-field-label">Work email</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username" @if ($errors->has('email')) aria-invalid="true" aria-describedby="login-email-error" @endif
                   class="block w-full border px-3 py-2.5 text-sm">
            @error('email') <p id="login-email-error" class="mt-1.5 text-sm font-medium text-rose-700">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="password" class="ui-field-label">Password</label>
            <input id="password" name="password" type="password" required autocomplete="current-password"
                   class="block w-full border px-3 py-2.5 text-sm">
            @error('password') <p class="mt-1.5 text-sm font-medium text-rose-700">{{ $message }}</p> @enderror
        </div>

        <label class="flex min-h-10 items-center gap-2 text-sm text-slate-600">
            <input type="checkbox" name="remember" value="1" class="size-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
            Remember me
        </label>

        <button type="submit" class="ui-button-primary w-full py-3">Sign in</button>

        <p class="text-center text-sm"><a href="{{ route('password.request') }}" class="font-semibold text-indigo-700 underline decoration-indigo-200 underline-offset-4 hover:text-indigo-900">Forgot your password?</a></p>
    </form>
@endsection
