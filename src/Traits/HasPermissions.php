<?php

namespace Akindutire\Authorization\Traits;

use Akindutire\Authorization\Facades\EntityPermission;

/**
 * Trait to add permission functionality to Eloquent models
 *
 * Add this trait to any model that should have permission checking capabilities.
 * The model must have 'allowed_permissions' and 'revoked_permissions' columns.
 *
 * Usage:
 * class User extends Model {
 *     use HasPermissions;
 * }
 *
 * $user->updatePermission(['can_view', 'can_edit']);
 */
trait HasPermissions
{
    /**
     * Update the allowed permissions for this model
     *
     * @param array $permissions Array of permission strings
     * @return bool
     */
    public function updatePermission(array $permissions = []): bool
    {
        $permissionsString = implode(',', array_filter($permissions));

        return $this->update([
            'allowed_permissions' => $permissionsString,
        ]);
    }

    /**
     * Set allowed permissions from a role
     *
     * @param string $role
     * @return bool
     */
    public function setPermissionsFromRole(string $role): bool
    {
        $permissions = EntityPermission::getDefaultActions($role);

        return $this->updatePermission($permissions);
    }

    /**
     * Add a permission to allowed permissions
     *
     * @param string $permission
     * @return bool
     */
    public function grantPermission(string $permission): bool
    {
        $currentPermissions = $this->getAllowedPermissions();

        if (!in_array($permission, $currentPermissions)) {
            $currentPermissions[] = $permission;
        }

        return $this->updatePermission($currentPermissions);
    }

    /**
     * Remove a permission by adding it to revoked permissions
     *
     * @param string $permission
     * @return bool
     */
    public function revokePermission(string $permission): bool
    {
        $currentRevoked = $this->getRevokedPermissions();

        if (!in_array($permission, $currentRevoked)) {
            $currentRevoked[] = $permission;
        }

        $revokedString = implode(',', array_filter($currentRevoked));

        return $this->update([
            'revoked_permissions' => $revokedString,
        ]);
    }

    /**
     * Get allowed permissions as an array
     *
     * @return array
     */
    public function getAllowedPermissions(): array
    {
        return array_filter(explode(',', $this->allowed_permissions ?? ''));
    }

    /**
     * Get revoked permissions as an array
     *
     * @return array
     */
    public function getRevokedPermissions(): array
    {
        return array_filter(explode(',', $this->revoked_permissions ?? ''));
    }

    /**
     * Get the effective permissions (allowed - revoked)
     *
     * @return array
     */
    public function getEffectivePermissions(): array
    {
        return array_diff(
            $this->getAllowedPermissions(),
            $this->getRevokedPermissions()
        );
    }

    /**
     * Check if the model has a specific permission
     *
     * @param string $permission
     * @return bool
     */
    public function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->getEffectivePermissions());
    }

    /**
     * Check if the model has any of the specified permissions
     *
     * @param array $permissions
     * @return bool
     */
    public function hasAnyPermission(array $permissions): bool
    {
        return count(array_intersect($permissions, $this->getEffectivePermissions())) > 0;
    }

    /**
     * Check if the model has all of the specified permissions
     *
     * @param array $permissions
     * @return bool
     */
    public function hasAllPermissions(array $permissions): bool
    {
        return count(array_intersect($permissions, $this->getEffectivePermissions())) === count($permissions);
    }
}
