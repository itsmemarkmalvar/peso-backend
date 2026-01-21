<?php

namespace App\Traits;

use App\Enums\UserRole;

trait HasRoles
{
    /**
     * Check if user has specific role
     */
    public function hasRole(UserRole $role): bool
    {
        return $this->role === $role->value;
    }

    /**
     * Check if user has any of the specified roles
     */
    public function hasAnyRole(array $roles): bool
    {
        foreach ($roles as $role) {
            if ($this->hasRole($role)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if user is admin
     */
    public function isAdmin(): bool
    {
        return $this->hasRole(UserRole::ADMIN);
    }

    /**
     * Check if user is intern
     */
    public function isIntern(): bool
    {
        return $this->hasRole(UserRole::INTERN);
    }

    /**
     * Check if user is coordinator (combined coordinator/supervisor role)
     */
    public function isCoordinator(): bool
    {
        return $this->hasRole(UserRole::COORDINATOR);
    }
}
