<?php

namespace App\Policies;

use App\Models\User;

/**
 * Accounts verwaltet nur das Familienoberhaupt (Admin).
 */
class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->isAdmin();
    }

    public function create(User $actor): bool
    {
        return $actor->isAdmin();
    }

    public function update(User $actor, User $user): bool
    {
        return $actor->isAdmin();
    }

    /**
     * Den eigenen Account zu löschen würde den Admin aussperren.
     */
    public function delete(User $actor, User $user): bool
    {
        return $actor->isAdmin() && ! $actor->is($user);
    }
}
