<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CreateUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Http\Requests\Admin\UpdateUserStatusRequest;
use App\Http\Requests\Admin\UserIndexRequest;
use App\Models\Department;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(UserIndexRequest $request): JsonResponse|View
    {
        $this->authorize('viewAny', User::class);
        $filters = $request->validated();

        $query = User::query()->with('department:id,name')->select([
            'id', 'name', 'email', 'role', 'is_active', 'department_id', 'employee_id', 'created_at', 'updated_at',
        ]);
        $query->when($filters['search'] ?? null, fn ($q, $search) => $q->where(fn ($nested) => $nested
            ->where('name', 'like', "%{$search}%")
            ->orWhere('email', 'like', "%{$search}%")
            ->orWhere('employee_id', 'like', "%{$search}%")));
        $query->when($filters['role'] ?? null, fn ($q, $role) => $q->where('role', $role));
        $query->when($filters['department_id'] ?? null, fn ($q, $id) => $q->where('department_id', $id));
        $query->when(isset($filters['active']), fn ($q) => $q->where('is_active', $filters['active'] === 'active'));
        $users = $query->orderBy($filters['sort'] ?? 'name', $filters['direction'] ?? 'asc')->paginate(25)->withQueryString();

        if ($request->expectsJson()) {
            return response()->json($users);
        }

        return view('admin.users.index', [
            'users' => $users,
            'filters' => $filters,
            'departments' => Department::query()->orderBy('name')->get(['id', 'name']),
            'roles' => UserRole::cases(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', User::class);

        return view('admin.users.form', [
            'user' => new User,
            'departments' => Department::active()->orderBy('name')->get(['id', 'name']),
            'roles' => UserRole::cases(),
            'creating' => true,
        ]);
    }

    public function store(CreateUserRequest $request): JsonResponse|RedirectResponse
    {
        $data = $request->validated();
        $user = new User;
        $user->name = $data['name'];
        $user->email = $data['email'];
        $user->password = Hash::make($data['password']);
        $user->role = UserRole::from($data['role']);
        $user->department_id = $data['department_id'] ?? null;
        $user->employee_id = $data['employee_id'] ?? null;
        $user->phone = $data['phone'] ?? null;
        $user->is_active = true;
        $user->save();

        if ($request->expectsJson()) {
            return response()->json(['data' => ['id' => $user->id]], 201);
        }

        return redirect()->route('admin.users.show', $user)->with('status', 'User created.');
    }

    public function show(User $user): JsonResponse|View
    {
        $this->authorize('view', $user);
        $user->load('department:id,name');

        if (request()->expectsJson()) {
            return response()->json(['data' => $user->only(['id', 'name', 'email', 'role', 'is_active', 'department_id', 'employee_id', 'phone', 'created_at', 'updated_at']) + [
                'department' => $user->department?->name,
            ]]);
        }

        return view('admin.users.show', compact('user'));
    }

    public function edit(User $user): View
    {
        $this->authorize('update', $user);

        return view('admin.users.form', [
            'user' => $user,
            'departments' => Department::active()->orderBy('name')->get(['id', 'name']),
            'roles' => UserRole::cases(),
            'creating' => false,
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): JsonResponse|RedirectResponse
    {
        $data = $request->validated();

        foreach (['name', 'email', 'employee_id', 'phone', 'department_id'] as $field) {
            if (array_key_exists($field, $data)) {
                $user->{$field} = $data[$field];
            }
        }
        if (array_key_exists('role', $data)) {
            $user->role = UserRole::from($data['role']);
            if (! array_key_exists('department_id', $data) && ! $user->role->isAdmin() && ! $user->department_id) {
                $user->department_id = Department::query()->active()->orderBy('id')->value('id');
            }
        }
        if (array_key_exists('is_active', $data)) {
            $user->is_active = (bool) $data['is_active'];
        }
        $user->save();
        if (array_key_exists('role', $data) || (array_key_exists('is_active', $data) && ! $user->is_active)) {
            $user->tokens()->delete();
        }

        if ($request->expectsJson()) {
            return response()->json(['data' => [
                'id' => $user->id,
                'role' => $user->role->value,
                'is_active' => $user->is_active,
                'department_id' => $user->department_id,
            ]]);
        }

        return redirect()->route('admin.users.show', $user)->with('status', 'User updated.');
    }

    public function status(UpdateUserStatusRequest $request, User $user): JsonResponse|RedirectResponse
    {
        $data = $request->validated();
        $user->is_active = (bool) $data['is_active'];
        $user->save();
        if (! $user->is_active) {
            $user->tokens()->delete();
        }

        return $request->expectsJson()
            ? response()->json(['data' => ['id' => $user->id, 'is_active' => $user->is_active]])
            : back()->with('status', $user->is_active ? 'User activated.' : 'User deactivated.');
    }
}
