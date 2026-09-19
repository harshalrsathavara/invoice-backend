<?php

namespace App\Policies;

use App\Models\Business;
use App\Models\User;

class BusinessPolicy
{
    /** The admin sees everything; an owner sees only their own. */
    public function before(User $user, string $ability): ?bool
    {
        return $user->is_admin ? true : null;
    }

    /** Only the admin panel adds a company on someone's behalf. */
    public function create(User $user): bool
    {
        return $user->is_admin;
    }

    public function view(User $user, Business $business): bool
    {
        return $business->user_id === $user->id;
    }

    public function update(User $user, Business $business): bool
    {
        return $business->user_id === $user->id;
    }

    public function delete(User $user, Business $business): bool
    {
        return $business->user_id === $user->id;
    }
}
