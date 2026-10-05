@extends('layouts.app')
@section('title', 'Users')
@section('topline', 'User management')
@section('content')
    <section class="ui-page-toolbar mb-6 flex flex-wrap items-end justify-between gap-4">
        <div>
            <p class="ui-eyebrow">Directory</p>
            <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-950">Users &amp; technicians</h1>
            <p class="mt-1 text-sm text-slate-500">Manage roles, account status, and departments.</p>
        </div>
        <a href="{{ route('admin.users.create') }}" class="ui-button-primary">Create user <span aria-hidden="true">+</span></a>
    </section>

    <section class="ticket-filter-card mb-6 overflow-hidden rounded-xl border border-gray-100 bg-white shadow-md" aria-label="Search and filter users">
        <div class="mb-4 flex items-center gap-3 bg-indigo-50/50 px-4 pb-3 pt-3.5 sm:px-5">
            <span class="grid size-9 place-items-center rounded-xl bg-indigo-100 text-indigo-600" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="size-5"><path stroke-linecap="round" stroke-linejoin="round" d="M4 5h16l-6.5 7.5v5l-3 1.5v-6.5L4 5Z" /></svg>
            </span>
            <div>
                <h2 class="text-sm font-bold text-slate-900">Filter users</h2>
                <p class="mt-0.5 text-xs text-slate-500">Narrow the directory by role, department, or account status</p>
            </div>
        </div>
        <form method="GET" class="grid grid-cols-1 gap-4 px-4 pb-4 sm:grid-cols-2 sm:px-5 sm:pb-5 xl:grid-cols-4">
            <label class="sm:col-span-2 xl:col-span-4">
                <span class="mb-1.5 block text-[11px] font-bold uppercase tracking-wider text-slate-500">Search</span>
                <input name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Name, email, employee ID" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3.5 py-2.5 text-sm text-slate-800 shadow-sm transition placeholder:text-slate-400 focus:border-indigo-500 focus:bg-white focus:ring-2 focus:ring-indigo-500/15 focus:outline-none">
            </label>
            <label>
                <span class="mb-1.5 block text-[11px] font-bold uppercase tracking-wider text-slate-500">Role</span>
                <select name="role" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3.5 py-2.5 text-sm text-slate-800 shadow-sm transition focus:border-indigo-500 focus:bg-white focus:ring-2 focus:ring-indigo-500/15 focus:outline-none">
                    <option value="">All roles</option>
                    @foreach ($roles as $role)<option value="{{ $role->value }}" @selected(($filters['role'] ?? '') === $role->value)>{{ $role->label() }}</option>@endforeach
                </select>
            </label>
            <label>
                <span class="mb-1.5 block text-[11px] font-bold uppercase tracking-wider text-slate-500">Department</span>
                <select name="department_id" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3.5 py-2.5 text-sm text-slate-800 shadow-sm transition focus:border-indigo-500 focus:bg-white focus:ring-2 focus:ring-indigo-500/15 focus:outline-none">
                    <option value="">All departments</option>
                    @foreach ($departments as $department)<option value="{{ $department->id }}" @selected((string) ($filters['department_id'] ?? '') === (string) $department->id)>{{ $department->name }}</option>@endforeach
                </select>
            </label>
            <label>
                <span class="mb-1.5 block text-[11px] font-bold uppercase tracking-wider text-slate-500">Account</span>
                <select name="active" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3.5 py-2.5 text-sm text-slate-800 shadow-sm transition focus:border-indigo-500 focus:bg-white focus:ring-2 focus:ring-indigo-500/15 focus:outline-none">
                    <option value="">All accounts</option>
                    <option value="active" @selected(($filters['active'] ?? '') === 'active')>Active</option>
                    <option value="inactive" @selected(($filters['active'] ?? '') === 'inactive')>Inactive</option>
                </select>
            </label>
            <label>
                <span class="mb-1.5 block text-[11px] font-bold uppercase tracking-wider text-slate-500">Sort by</span>
                <select name="sort" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3.5 py-2.5 text-sm text-slate-800 shadow-sm transition focus:border-indigo-500 focus:bg-white focus:ring-2 focus:ring-indigo-500/15 focus:outline-none">
                    <option value="name" @selected(($filters['sort'] ?? 'name') === 'name')>Name</option>
                    <option value="email" @selected(($filters['sort'] ?? '') === 'email')>Email</option>
                    <option value="role" @selected(($filters['sort'] ?? '') === 'role')>Role</option>
                    <option value="created_at" @selected(($filters['sort'] ?? '') === 'created_at')>Date added</option>
                </select>
            </label>
            <div class="flex items-center justify-end gap-3 pt-4 sm:col-span-2 xl:col-span-4">
                <div class="flex items-center gap-2">
                    <a href="{{ route('admin.users.index') }}" class="rounded-xl px-4 py-2.5 text-sm font-semibold text-slate-600 transition hover:bg-rose-50 hover:text-rose-600">Clear</a>
                    <button type="submit" class="ticket-filter-submit gap-2">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="size-4" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m4 5 6.5 7.5V19l3 1.5v-8L20 5H4Z" /></svg>
                        Apply filters
                    </button>
                </div>
            </div>
        </form>
    </section>

    <section class="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-[0_10px_30px_rgb(15_23_42/0.06)]" aria-label="User directory">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 bg-gradient-to-r from-white to-indigo-50/50 px-5 py-4 sm:px-6">
            <div>
                <h2 class="text-sm font-bold text-slate-900">Directory members</h2>
                <p class="mt-1 text-xs text-slate-500">Accounts and access at a glance</p>
            </div>
            <span class="rounded-full border border-indigo-100 bg-indigo-50 px-3 py-1 text-xs font-semibold text-indigo-700">{{ $users->count() }} shown</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[800px] text-left text-sm">
                <thead class="bg-slate-50/90 text-[10px] font-bold uppercase tracking-[.14em] text-slate-500">
                    <tr><th class="px-6 py-3.5">User</th><th class="px-5 py-3.5">Role</th><th class="px-5 py-3.5">Department</th><th class="px-5 py-3.5">Account status</th><th class="px-5 py-3.5">Added</th><th class="px-6 py-3.5"><span class="sr-only">Actions</span></th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($users as $user)
                        <tr class="group transition-colors hover:bg-indigo-50/35">
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-gradient-to-br from-indigo-100 to-violet-100 text-sm font-bold text-indigo-700 ring-1 ring-inset ring-indigo-200/70">{{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}</span>
                                    <div class="min-w-0"><a href="{{ route('admin.users.show', $user) }}" class="font-semibold text-slate-900 transition group-hover:text-indigo-700">{{ $user->name }}</a><span class="mt-0.5 block truncate text-xs text-slate-500">{{ $user->email }}{{ $user->employee_id ? ' · '.$user->employee_id : '' }}</span></div>
                                </div>
                            </td>
                            <td class="px-5 py-4"><span class="inline-flex rounded-full border px-2.5 py-1 text-xs font-semibold {{ $user->role->value === 'admin' ? 'border-violet-200 bg-violet-50 text-violet-700' : ($user->role->value === 'support' ? 'border-sky-200 bg-sky-50 text-sky-700' : 'border-slate-200 bg-slate-50 text-slate-700') }}">{{ $user->role->label() }}</span></td>
                            <td class="px-5 py-4"><span class="inline-flex items-center gap-2 text-slate-700"><span class="size-1.5 rounded-full bg-indigo-400"></span>{{ $user->department?->name ?? '—' }}</span></td>
                            <td class="px-5 py-4"><span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold {{ $user->is_active ? 'bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-200' : 'bg-slate-100 text-slate-600 ring-1 ring-inset ring-slate-200' }}"><span class="size-1.5 rounded-full {{ $user->is_active ? 'bg-emerald-500' : 'bg-slate-400' }}"></span>{{ $user->is_active ? 'Active' : 'Inactive' }}</span></td>
                            <td class="whitespace-nowrap px-5 py-4 text-slate-500">{{ $user->created_at->format('M j, Y') }}</td>
                            <td class="px-6 py-4 text-right"><a href="{{ route('admin.users.edit', $user) }}" class="inline-flex items-center gap-1 rounded-lg px-3 py-2 text-xs font-bold text-indigo-700 transition hover:bg-indigo-100 hover:text-indigo-900">Edit <span aria-hidden="true">→</span></a></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-6 py-16 text-center"><span class="mx-auto grid size-12 place-items-center rounded-2xl bg-indigo-50 text-indigo-500"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" class="size-6"><path stroke-linecap="round" stroke-linejoin="round" d="M16 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2m6-10a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm8 1v6m3-3h-6" /></svg></span><p class="mt-3 font-semibold text-slate-800">No users match these filters</p><p class="mt-1 text-sm text-slate-500">Try adjusting your search or filters.</p></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-slate-100 bg-white px-5 py-4 sm:px-6">{{ $users->links() }}</div>
    </section>
@endsection
