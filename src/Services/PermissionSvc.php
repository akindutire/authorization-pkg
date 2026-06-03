<?php

namespace Akindutire\Authorization\Services;

use Akindutire\Authorization\Attributes\Interfaces\SubjectModel;
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
    // model key for allowed permissions
    private string $roleAllowedPermissionLookupIndex = 'allowedPermissions';

    // model key for revoked permissions
    private string $subjectRevokedPermissionLookupIndex = 'revokedPermissions';

    // The current entity extracts being checked
    private ?SubjectModel $subject = null;

    // Memoization cache: stores resolved permissions for the current subject
    //
    // WHEN IT HELPS: Only useful for manual service reuse pattern:
    //   $svc = App::make(PermissionSvc::class)->subject($user, null, null);
    //   $svc->hasAny(['can_edit']);     // Computes permissions
    //   $svc->hasAll(['can_view']);     // Reuses cached permissions
    //
    // WHEN IT DOESN'T HELP: Attribute usage (normal flow):
    //   App::make(PermissionSvc::class)->subject($user, null, null)->hasAny([...]);
    //   ↑ Fresh instance = no reuse. Entity caching happens at attribute level instead.
    //
    // Cleared when subject changes to maintain accuracy
    private ?array $cachedResolvedPermissions = null;

    public function __construct() { }

    /**
     * Get the resolved permissions for the subject
     * (allowed_permissions minus revoked_permissions)
     *
     * Supports both JSON arrays and legacy CSV string formats.
     * Memoized to optimize manual service reuse (see $cachedResolvedPermissions comment).
     *
     * @return array Effective permissions after subtracting revoked from allowed
     */
    private function subjectResolvedPermission(): array
    {
        // Return memoized result if available (only helps with manual service reuse)
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
     * @return array
     */
    public function getDefaultActions(string $role): array
    {
        // Load from config if available
        $defaultActions = config("akindutire-authorization.abilities.{$role}", []);

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
     * Accepts either:
     * - SubjectModel instance (used internally by HasAny/HasAll attributes)
     * - Eloquent model with allowed_permissions and revoked_permissions (facade/manual use)
     *
     * Example:
     *   EntityPermission::subject($user)->hasAny(['can_edit'])
     *   EntityPermission::subject(new SubjectModel(['can_view'], []))->hasAny(['can_view'])
     *
     * @param object $subject The entity to check permissions for (SubjectModel or Eloquent model)
     * @return $this Fluent interface for method chaining
     */
    public function subject(
        object $subject
    ): static {
        // Convert Eloquent models to SubjectModel for internal use
        if (!($subject instanceof SubjectModel)) {
            $allowedCol = config('akindutire-authorization.column_names.allowed_permissions', 'allowed_permissions');
            $revokedCol = config('akindutire-authorization.column_names.revoked_permissions', 'revoked_permissions');

            $subject = new SubjectModel(
                $subject->{$allowedCol} ?? [],
                $subject->{$revokedCol} ?? []
            );
        }

        // Clear memoization cache when subject changes
        // Prevents stale permission data if same service instance is reused
        if ($this->subject !== $subject) {
            $this->cachedResolvedPermissions = null;
        }

        $this->subject = $subject;
        return $this;
    }

    /**
     * Check if the subject has ANY of the specified actions/permissions
     *
     * Performance optimizations:
     * - array_flip() for O(1) lookups
     * - Short-circuit on first match (no need to check remaining permissions)
     *
     * Note: Entity-level caching happens in HasAny/HasAll attributes.
     * Permission resolution memoization only helps with manual service reuse.
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
     * - O(n + m) complexity
     *
     * Note: Entity-level caching happens in HasAny/HasAll attributes.
     * Permission resolution memoization only helps with manual service reuse.
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
