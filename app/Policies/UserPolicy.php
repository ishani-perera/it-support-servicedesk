<?php

namespace App\Policies;

use App\Models\User;

/**
 * User management. Only Admin manages other accounts.
 *
 * Privileged fields have their own abilities so each can be checked
 * independently of the others:
 *   updateRole, updateStatus (activate/deactivate), updateDepartment.
 *
 * Safety rules: nobody can change their OWN role or deactivate THEMSELVES —
 * that prevents privilege escalation by self-edit, and an admin locking the
 * last admin account out by accident. (Because the actor must be an admin
 * to change anyone's role, at least one admin always remains.)
 */
class UserPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->is_active ? null : false;
    }

    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, User $target): bool
    {
        return $user->isAdmin() || $user->is($target);
    }

    /** Self-service profile fields only (name, phone). */
    public function update(User $user, User $target): bool
    {
        return $user->isAdmin() || $user->is($target);
    }

    public function updateRole(User $user, User $target): bool
    {
        return $user->isAdmin() && ! $user->is($target);
    }

    public function updateStatus(User $user, User $target): bool
    {
        return $user->isAdmin() && ! $user->is($target);
    }

    public function updateDepartment(User $user, User $target): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, User $target): bool
    {
        return $user->isAdmin() && ! $user->is($target);
    }
}
