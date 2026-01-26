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
        return $this->role === $role;
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
     * Check if user is supervisor
     */
    public function isSupervisor(): bool
    {
        return $this->hasRole(UserRole::SUPERVISOR);
    }

    /**
     * Check if user is GIP
     */
    public function isGip(): bool
    {
        return $this->hasRole(UserRole::GIP);
    }

    /**
     * Check if user is intern or GIP (same restrictions)
     */
    public function isInternOrGip(): bool
    {
        return $this->hasAnyRole([UserRole::INTERN, UserRole::GIP]);
    }
}
