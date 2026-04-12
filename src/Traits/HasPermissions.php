<?php

namespace Akindutire\Authorization\Traits;

use Akindutire\Authorization\Facades\EntityPermission;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Trait to add permission functionality to Eloquent models
 *
 * Add this trait to any model that should have permission checking capabilities.
 * The model must have 'allowed_permissions' and 'revoked_permissions' columns.
 *
 * Supports both JSON and legacy CSV formats for permissions.
 * Automatically invalidates caches when permissions change.
 *
 * Usage:
 * class User extends Model {
 *     use HasPermissions;
 *
 *     // Required: cast permission columns to array for JSON support
 *     protected $casts = [
 *         'allowed_permissions' => 'array',
 *         'revoked_permissions' => 'array',
 *     ];
 * }
 *
 * $user->updatePermission(['can_view', 'can_edit']);
 */
trait HasPermissions
{
    /**
     * Boot the HasPermissions trait
     *
     * Registers model event listeners for automatic cache invalidation
     * when permission columns are modified
     */
    protected static function bootHasPermissions(): void
    {
        // Listen for model updates
        static::updated(function ($model) {
            // Only clear cache if permission columns actually changed
            if ($model->isDirty(['allowed_permissions', 'revoked_permissions'])) {
                // Clear entity cache (used by attributes for subject lookup)
                $entityCacheKey = sprintf(
                    'entity.%s.id.%s',
                    str_replace('\\', '.', get_class($model)),
                    $model->getKey()
                );
                Cache::forget($entityCacheKey);

                // Clear caches for additional lookup properties (uuid, email, etc.)
                $cacheKeys = config('akindutire-authorization.cache_keys', ['uuid', 'email', 'slug']);
                foreach ($cacheKeys as $property) {
                    if (isset($model->$property)) {
                        $cacheKey = sprintf(
                            'entity.%s.%s.%s',
                            str_replace('\\', '.', get_class($model)),
                            $property,
                            $model->$property
                        );
                        Cache::forget($cacheKey);
                    }
                }
            }
        });
    }
    /**
     * Update the allowed permissions for this model
     *
     * Automatically handles JSON vs CSV based on model $casts configuration.
     * If 'allowed_permissions' is cast to 'array', stores as JSON.
     * Otherwise, stores as legacy CSV format.
     *
     * @param array $permissions Array of permission strings (e.g., ['can_edit', 'can_delete'])
     * @return bool True if update succeeded
     */
    public function updatePermission(array $permissions = []): bool
    {
        // Clean the permissions array:
        // - array_unique: remove duplicates
        // - array_filter: remove empty strings, null values
        // - array_values: re-index array (prevents JSON object conversion)
        $cleaned = array_values(array_filter(array_unique($permissions)));

        // Laravel automatically converts to JSON if column is cast as 'array'
        // Otherwise, falls back to CSV string for backward compatibility
        return $this->update([
            'allowed_permissions' => $cleaned,
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
     * Add a permission to allowed permissions (atomic operation)
     *
     * Uses database-level JSON operations when possible to prevent race conditions.
     * Falls back to pessimistic locking for non-JSON columns.
     *
     * Race condition example this prevents:
     *   Admin A grants 'can_edit' at time T
     *   Admin B grants 'can_delete' at time T+1ms
     *   Without atomicity: one permission would be lost
     *
     * @param string $permission Permission to grant (e.g., 'can_edit')
     * @return bool True if operation succeeded
     */
    public function grantPermission(string $permission): bool
    {
        $driver = DB::getDriverName();

        // MySQL: Use JSON_ARRAY_APPEND for atomic operation
        if ($driver === 'mysql' && $this->hasCast('allowed_permissions', ['array', 'json'])) {
            return (bool) $this->newQuery()
                ->where($this->getKeyName(), $this->getKey())
                ->update([
                    // COALESCE handles null columns, '$' appends to root array
                    'allowed_permissions' => DB::raw(
                        "JSON_ARRAY_APPEND(COALESCE(allowed_permissions, JSON_ARRAY()), '$', ?)",
                        [$permission]
                    )
                ]);
        }

        // PostgreSQL: Use JSONB concatenation operator
        if ($driver === 'pgsql' && $this->hasCast('allowed_permissions', ['array', 'json'])) {
            return (bool) $this->newQuery()
                ->where($this->getKeyName(), $this->getKey())
                ->update([
                    // COALESCE handles null, || concatenates arrays
                    'allowed_permissions' => DB::raw(
                        "COALESCE(allowed_permissions, '[]'::jsonb) || ?::jsonb",
                        [json_encode([$permission])]
                    )
                ]);
        }

        // Fallback: Use database transaction with row-level locking
        // lockForUpdate() prevents concurrent modifications to the same row
        return DB::transaction(function () use ($permission) {
            // SELECT ... FOR UPDATE locks the row until transaction completes
            $locked = static::where($this->getKeyName(), $this->getKey())
                ->lockForUpdate()
                ->first();

            $currentPermissions = $locked->getAllowedPermissions();

            // Only add if not already present
            if (!in_array($permission, $currentPermissions, true)) {
                $currentPermissions[] = $permission;
            }

            return $locked->updatePermission($currentPermissions);
        });
    }

    /**
     * Remove a permission by adding it to revoked permissions (atomic operation)
     *
     * Uses database-level JSON operations when possible to prevent race conditions.
     * Falls back to pessimistic locking for non-JSON columns.
     *
     * @param string $permission Permission to revoke (e.g., 'can_delete')
     * @return bool True if operation succeeded
     */
    public function revokePermission(string $permission): bool
    {
        $driver = DB::getDriverName();

        // MySQL: Use JSON_ARRAY_APPEND for atomic operation
        if ($driver === 'mysql' && $this->hasCast('revoked_permissions', ['array', 'json'])) {
            return (bool) $this->newQuery()
                ->where($this->getKeyName(), $this->getKey())
                ->update([
                    'revoked_permissions' => DB::raw(
                        "JSON_ARRAY_APPEND(COALESCE(revoked_permissions, JSON_ARRAY()), '$', ?)",
                        [$permission]
                    )
                ]);
        }

        // PostgreSQL: Use JSONB concatenation operator
        if ($driver === 'pgsql' && $this->hasCast('revoked_permissions', ['array', 'json'])) {
            return (bool) $this->newQuery()
                ->where($this->getKeyName(), $this->getKey())
                ->update([
                    'revoked_permissions' => DB::raw(
                        "COALESCE(revoked_permissions, '[]'::jsonb) || ?::jsonb",
                        [json_encode([$permission])]
                    )
                ]);
        }

        // Fallback: Use database transaction with row-level locking
        return DB::transaction(function () use ($permission) {
            $locked = static::where($this->getKeyName(), $this->getKey())
                ->lockForUpdate()
                ->first();

            $currentRevoked = $locked->getRevokedPermissions();

            if (!in_array($permission, $currentRevoked, true)) {
                $currentRevoked[] = $permission;
            }

            // Will be auto-cast to JSON or CSV based on model configuration
            return $locked->update([
                'revoked_permissions' => $currentRevoked,
            ]);
        });
    }

    /**
     * Get allowed permissions as an array
     *
     * Handles both JSON arrays and legacy CSV strings.
     * Laravel automatically decodes if column is cast as 'array'.
     *
     * @return array Array of permission strings
     */
    public function getAllowedPermissions(): array
    {
        $permissions = $this->allowed_permissions;

        // Already an array (from JSON cast or manual array assignment)
        if (is_array($permissions)) {
            return array_filter($permissions);
        }

        // Legacy CSV format or null
        return array_filter(explode(',', $permissions ?? ''));
    }

    /**
     * Get revoked permissions as an array
     *
     * Handles both JSON arrays and legacy CSV strings.
     * Laravel automatically decodes if column is cast as 'array'.
     *
     * @return array Array of permission strings
     */
    public function getRevokedPermissions(): array
    {
        $permissions = $this->revoked_permissions;

        // Already an array (from JSON cast or manual array assignment)
        if (is_array($permissions)) {
            return array_filter($permissions);
        }

        // Legacy CSV format or null
        return array_filter(explode(',', $permissions ?? ''));
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
