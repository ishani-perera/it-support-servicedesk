<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;

/**
 * Phase 03 authorization boundary for user management (no admin UI yet).
 * Routes are behind `role:admin`; the policy re-checks every action.
 */
class UserController extends Controller
{
    public function index(): JsonResponse
    {
        $this->authorize('viewAny', User::class);

        $users = User::query()
            ->orderBy('id')
            ->paginate(25, ['id', 'name', 'email', 'role', 'is_active', 'department_id']);

        return response()->json($users);
    }

    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $validated = $request->validated();

        // role / is_active are NOT mass-assignable (by design). Trusted code,
        // reached only after UserPolicy approved each field, sets them explicitly.
        if (array_key_exists('role', $validated)) {
            $user->role = UserRole::from($validated['role']);
        }
        if (array_key_exists('is_active', $validated)) {
            $user->is_active = (bool) $validated['is_active'];
        }
        if (array_key_exists('department_id', $validated)) {
            $user->department_id = $validated['department_id'];
        }

        $user->save();

        return response()->json(['data' => [
            'id' => $user->id,
            'role' => $user->role->value,
            'is_active' => $user->is_active,
            'department_id' => $user->department_id,
        ]]);
    }
}
