@extends('layouts.guest')

@section('title', 'Reset password')

@section('content')
    <form method="POST" action="{{ route('password.store') }}" class="space-y-4">
        @csrf

        <input type="hidden" name="token" value="{{ $token }}">

        <div>
            <label for="email" class="ui-field-label">Work email</label>
            <input id="email" name="email" type="email" value="{{ old('email', $email) }}" required autocomplete="username"
                   class="block w-full border px-3 py-2.5 text-sm">
            @error('email') <p class="mt-1.5 text-sm font-medium text-rose-700">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="password" class="ui-field-label">New password</label>
            <input id="password" name="password" type="password" required autocomplete="new-password"
                   class="block w-full border px-3 py-2.5 text-sm">
            <p class="mt-1 text-xs text-slate-500">At least 12 characters, with upper and lower case letters and a number.</p>
            @error('password') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="password_confirmation" class="ui-field-label">Confirm new password</label>
            <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password"
                   class="block w-full border px-3 py-2.5 text-sm">
        </div>

        <button type="submit" class="ui-button-primary w-full py-3">Reset password</button>
    </form>
@endsection
