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
    // Column name for allowed permissions (configurable per entity type)
    private string $roleAllowedPermissionLookupIndex;

    // Column name for revoked permissions (configurable per entity type)
    private string $subjectRevokedPermissionLookupIndex;

    // The current entity being checked (User, Article, TeamMember, etc.)
    private ?Model $subject = null;

    // Memoization cache: stores resolved permissions for the current subject
    // Prevents redundant explode/array_diff operations within same request
    // Cleared when subject changes to maintain accuracy
    private ?array $cachedResolvedPermissions = null;

    public function __construct()
    {
        $this->roleAllowedPermissionLookupIndex = config('authorization.column_names.allowed_permissions', 'allowed_permissions');
        $this->subjectRevokedPermissionLookupIndex = config('authorization.column_names.revoked_permissions', 'revoked_permissions');
    }

    /**
     * Get the resolved permissions for the subject
     * (allowed_permissions minus revoked_permissions)
     *
     * Uses memoization to prevent redundant computation within same request.
     * Supports both JSON arrays and legacy CSV string formats.
     *
     * @return array Effective permissions after subtracting revoked from allowed
     */
    private function subjectResolvedPermission(): array
    {
        // Return memoized result if available
        // Prevents redundant string parsing when multiple permission checks occur
        if ($this->cachedResolvedPermissions !== null) {
            return $this->cachedResolvedPermissions;
        }

        // Fetch raw permission data from the subject model
        $rolePermissions = $this->subject->{$this->roleAllowedPermissionLookupIndex};
        $subjectRevokedPermissions = $this->subject->{$this->subjectRevokedPermissionLookupIndex};

        // Normalize permissions to arrays (handles JSON, CSV, null)
        $allowed = $this->normalizePermissions($rolePermissions);
        $revoked = $this->normalizePermissions($subjectRevokedPermissions);

        // Calculate effective permissions: allowed - revoked
        // array_diff returns values in $allowed that are NOT in $revoked
        $this->cachedResolvedPermissions = array_diff($allowed, $revoked);

        return $this->cachedResolvedPermissions;
    }

    /**
     * Normalize permission data into a clean array format
     *
     * Handles multiple input formats:
     * - JSON array (recommended): ["can_edit", "can_delete"]
     * - JSON string: '["can_edit", "can_delete"]'
     * - Legacy CSV: "can_edit,can_delete"
     * - Null/empty: returns []
     *
     * Filters out empty strings, whitespace, and null values for security
     *
     * @param mixed $value Raw permission data from database
     * @return array Clean array of permission strings
     */
    private function normalizePermissions($value): array
    {
        // Handle null, empty string, or empty JSON array
        if (is_null($value) || $value === '' || $value === '[]') {
            return [];
        }

        // Already an array (Laravel auto-casts JSON columns)
        if (is_array($value)) {
            // Filter out empty strings, null, and whitespace-only entries
            // array_values re-indexes to prevent gaps in array keys
            return array_values(array_filter($value, fn($v) => is_string($v) && trim($v) !== ''));
        }

        // JSON string format (starts with '[')
        if (is_string($value) && str_starts_with(trim($value), '[')) {
            $decoded = json_decode($value, true);

            // Handle JSON decode errors gracefully
            if (json_last_error() !== JSON_ERROR_NONE) {
                return []; // Invalid JSON = no permissions (fail-safe)
            }

            // Recursively filter in case JSON contains nested structures
            return is_array($decoded)
                ? array_values(array_filter($decoded, fn($v) => is_string($v) && trim($v) !== ''))
                : [];
        }

        // Legacy CSV format: "can_edit,can_delete,can_view"
        // Split by comma, filter empty strings
        return array_values(array_filter(
            explode(',', $value),
            fn($v) => trim($v) !== ''
        ));
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
     * This method is thread-safe: each call creates/modifies a unique service instance.
     * Different entity types can use different column names without interference.
     *
     * Example:
     *   Article::find(1) with 'capabilities' column
     *   User::find(5) with 'allowed_permissions' column
     *
     * @param Model $subject The entity to check (User, Article, TeamMember, etc.)
     * @param string|null $roleAllowedLookupIndex Column name for allowed permissions
     * @param string|null $subjectRevokedLookupIndex Column name for revoked permissions
     * @return $this Fluent interface for method chaining
     * @throws \Exception If required columns don't exist on the model
     */
    public function subject(
        Model $subject,
        ?string $roleAllowedLookupIndex = null,
        ?string $subjectRevokedLookupIndex = null
    ): static {
        // Clear memoization cache when subject changes
        // Prevents stale permission data if same service instance is reused
        if ($this->subject !== $subject) {
            $this->cachedResolvedPermissions = null;
        }

        // Use provided column names or fall back to config defaults
        $roleAllowedLookupIndex = $roleAllowedLookupIndex ?? $this->roleAllowedPermissionLookupIndex;
        $subjectRevokedLookupIndex = $subjectRevokedLookupIndex ?? $this->subjectRevokedPermissionLookupIndex;
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
     * Performance optimizations:
     * - array_flip() for O(1) lookups instead of O(n) in_array()
     * - Short-circuit on first match (no need to check remaining permissions)
     * - Memoized permission resolution (via subjectResolvedPermission)
     *
     * @param array $actions Permission strings to check (e.g., ['can_edit', 'can_delete'])
     * @return bool True if subject has at least one of the specified permissions
     * @throws \Exception If subject hasn't been set via subject() method
     */
    public function hasAny(array $actions): bool
    {
        // Ensure subject was set before attempting permission check
        if (!$this->subject) {
            throw new \Exception("A subject is required to validate an action, kindly attach an object");
        }

        // Flatten nested arrays (handles cases like [['can_edit'], 'can_delete'])
        // array_walk_recursive descends into nested structures
        $flattenedActions = [];
        array_walk_recursive($actions, function ($a) use (&$flattenedActions) {
            $flattenedActions[] = $a;
        });

        // No permissions to check = deny by default
        if (empty($flattenedActions)) {
            return false;
        }

        // Flip subject permissions to use keys for O(1) isset() lookups
        // array_flip(['can_edit', 'can_delete']) => ['can_edit' => 0, 'can_delete' => 1]
        $subjectPerms = array_flip($this->subjectResolvedPermission());

        // Short-circuit: return true on FIRST match found
        // No need to iterate through remaining permissions
        foreach ($flattenedActions as $action) {
            if (isset($subjectPerms[$action])) {
                return true; // Found at least one matching permission
            }
        }

        // No matching permissions found
        return false;
    }

    /**
     * Check if the subject has ALL of the specified actions/permissions
     *
     * Performance optimizations:
     * - Uses array_intersect_key for efficient set comparison
     * - O(n + m) complexity instead of O(n × m) nested loops
     * - Memoized permission resolution (via subjectResolvedPermission)
     *
     * @param array $actions Permission strings to check (e.g., ['can_edit', 'can_delete'])
     * @return bool True only if subject has ALL specified permissions
     * @throws \Exception If subject hasn't been set via subject() method
     */
    public function hasAll(array $actions): bool
    {
        // Ensure subject was set before attempting permission check
        if (!$this->subject) {
            throw new \Exception("A subject is required to validate an action, kindly attach an object");
        }

        // Flatten nested arrays (handles cases like [['can_edit'], 'can_delete'])
        $flattenedActions = [];
        array_walk_recursive($actions, function ($a) use (&$flattenedActions) {
            $flattenedActions[] = $a;
        });

        // No permissions to check = deny by default
        if (empty($flattenedActions)) {
            return false;
        }

        // Flip both arrays to use keys for efficient comparison
        // array_flip(['can_edit', 'can_delete']) => ['can_edit' => 0, 'can_delete' => 1]
        $subjectPerms = array_flip($this->subjectResolvedPermission());
        $requiredPerms = array_flip($flattenedActions);

        // array_intersect_key returns keys that exist in BOTH arrays
        // If intersection count equals required count, subject has ALL permissions
        // Example: required = [can_edit, can_delete], subject = [can_edit, can_delete, can_view]
        //          intersection = [can_edit, can_delete] => count(2) === count(2) => true
        return count(array_intersect_key($requiredPerms, $subjectPerms)) === count($requiredPerms);
    }
}
