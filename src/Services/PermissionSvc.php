<?php

namespace Akindutire\Authorization\Services;

use Akindutire\Authorization\Enums\AppActions;
use Illuminate\Database\Eloquent\Model;

/**
 * Permission Service for checking subject permissions
 *
 * This service validates if a subject (any Eloquent model) has specific permissions.
 * Permissions are resolved by: allowed_permissions - revoked_permissions
 */
class PermissionSvc
{

    private string $roleAllowedPermissionLookupIndex;
    private string $subjectRevokedPermissionLookupIndex;
    private ?Model $subject = null;

    public function __construct()
    {
        $this->roleAllowedPermissionLookupIndex = config('authorization.column_names.allowed_permissions', 'allowed_permissions');
        $this->subjectRevokedPermissionLookupIndex = config('authorization.column_names.revoked_permissions', 'revoked_permissions');
    }

    /**
     * Get the resolved permissions for the subject
     * (allowed_permissions minus revoked_permissions)
     *
     * @return array
     */
    private function subjectResolvedPermission(): array
    {
        $rolePermissions = $this->subject->{$this->roleAllowedPermissionLookupIndex};
        $subjectRevokedPermissions = $this->subject->{$this->subjectRevokedPermissionLookupIndex};

        return array_diff(
            explode(',', $rolePermissions ?? ''),
            explode(',', $subjectRevokedPermissions ?? '')
        );
    }

    /**
     * Get default abilities for a application
     *
     * Override this method or configure via config file to customize
     * default permissions
     *
     * @param string $role
     * @return array
     */
    public function getAbilities(): array
    {
        // Load from config if available
        $defaultActions = config("authorization.abilities", []);

        if (!is_array($defaultActions)) {
            throw new \Exception("Default abilities must be an array, check your configuration");
        }
        
            return $defaultActions;
    }

    /**
     * Set the subject (model) to check permissions for
     *
     * @param Model $subject
     * @param string $roleAllowedLookupIndex Column name for allowed permissions
     * @param string $subjectRevokedLookupIndex Column name for revoked permissions
     * @return $this
     * @throws \Exception
     */
    public function subject(
        Model $subject,
        ?string $roleAllowedLookupIndex,
        ?string $subjectRevokedLookupIndex
    ): static {

        $roleAllowedLookupIndex = $roleAllowedLookupIndex??$this->roleAllowedPermissionLookupIndex;
        $subjectRevokedLookupIndex = $subjectRevokedLookupIndex??$this->subjectRevokedPermissionLookupIndex;
        if (!array_key_exists($roleAllowedLookupIndex, $subject->getAttributes())) {
            throw new \Exception(
                sprintf(
                    "'%s' is required to be a field of '%s', create a migration to include '%s' on the subject's database table",
                    $roleAllowedLookupIndex,
                    get_class($subject),
                    $roleAllowedLookupIndex
                )
            );
        }

        if (!array_key_exists($subjectRevokedLookupIndex, $subject->getAttributes())) {
            throw new \Exception(
                sprintf(
                    "'%s' is required to be a field of '%s', create a migration to include '%s' on the subject's database table",
                    $subjectRevokedLookupIndex,
                    get_class($subject),
                    $subjectRevokedLookupIndex
                )
            );
        }

        $this->subject = $subject;
        $this->roleAllowedPermissionLookupIndex = $roleAllowedLookupIndex;
        $this->subjectRevokedPermissionLookupIndex = $subjectRevokedLookupIndex;

        return $this;
    }

    /**
     * Check if the subject has ANY of the specified actions/permissions
     *
     * @param array $actions
     * @return bool
     * @throws \Exception
     */
    public function hasAny(array $actions): bool
    {
        if (!$this->subject) {
            throw new \Exception("A subject is required to validate an action, kindly attach an object");
        }

        // Flatten actions (in case of nested arrays)
        $flattenedActions = [];
        array_walk_recursive($actions, function ($a) use (&$flattenedActions) {
            $flattenedActions[] = $a;
        });

        if (count($flattenedActions) === 0) {
            return false;
        }

        $found = false;
        $subjectResolvedPerms = array_flip($this->subjectResolvedPermission());
        $actionIter = new \ArrayIterator($flattenedActions);

        while ($actionIter->valid()) {
            if (isset($subjectResolvedPerms[$actionIter->current()])) {
                $found = true;
                break;
            }

            $actionIter->next();
        }

        return $found;
    }

    /**
     * Check if the subject has ALL of the specified actions/permissions
     *
     * @param array $actions
     * @return bool
     * @throws \Exception
     */
    public function hasAll(array $actions): bool
    {
        if (!$this->subject) {
            throw new \Exception("A subject is required to validate an action, kindly attach an object");
        }

        // Flatten actions (in case of nested arrays)
        $flattenedActions = [];
        array_walk_recursive($actions, function ($a) use (&$flattenedActions) {
            $flattenedActions[] = $a;
        });

        if (count($flattenedActions) === 0) {
            return false;
        }

        $subjectResolvedPerms = array_flip($this->subjectResolvedPermission());
        $actionIter = new \ArrayIterator($flattenedActions);

        $permissionFound = 0;
        $totalActionsRequired = 0;

        while ($actionIter->valid()) {
            if (isset($subjectResolvedPerms[$actionIter->current()])) {
                $permissionFound += 1;
            }

            $totalActionsRequired += 1;
            $actionIter->next();
        }

        return $permissionFound === $totalActionsRequired;
    }
}
