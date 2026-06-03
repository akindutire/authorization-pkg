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
 * Automatically invalidates caches when permissions change.
 *
 * Usage:
 * class User extends Model {
 *     use HasPermissions;
 *
 *     // Optional: Casts are automatically added by the trait
 *     // But you can override them if needed:
 *     // protected $casts = [
 *     //     'allowed_permissions' => 'array',
 *     //     'revoked_permissions' => 'array',
 *     // ];
 * }
 *
 * $user->updatePermission(['can_view', 'can_edit']);
 */
trait HasPermissions
{
    /**
     * Initialize the HasPermissions trait for a model instance.
     *
     * This method is automatically called by Laravel when a model is instantiated.
     * It ensures that permission columns are cast to arrays for JSON support.
     *
     * If the user hasn't manually defined casts for these columns, we add them automatically.
     *
     * @return void
     */
    protected function initializeHasPermissions(): void
    {
        // Get column names from config
        $allowedColumn = config('akindutire-authorization.column_names.allowed_permissions', 'allowed_permissions');
        $revokedColumn = config('akindutire-authorization.column_names.revoked_permissions', 'revoked_permissions');

        // Get current casts
        $casts = $this->getCasts();

        // Auto-cast allowed_permissions to array if not already cast
        if (!isset($casts[$allowedColumn])) {
            $this->casts[$allowedColumn] = 'array';
        }

        // Auto-cast revoked_permissions to array if not already cast
        if (!isset($casts[$revokedColumn])) {
            $this->casts[$revokedColumn] = 'array';
        }
    }

    /**
     * Validate permission array size to prevent memory issues
     *
     * PROBLEM: Large permission arrays (>10KB) can cause:
     *   - High memory consumption (>512MB per PHP process)
     *   - Slow JSON encoding/decoding
     *   - Database query timeouts
     *   - Cache storage inefficiency
     *
     * This validation enforces configurable limits on:
     *   1. Total JSON size in bytes
     *   2. Number of individual permissions
     *
     * @param array $permissions Array of permission strings to validate
     * @throws \InvalidArgumentException If permissions exceed configured limits
     * @return void
     */
    protected function validatePermissionSize(array $permissions): void
    {
        // Check if validation is enabled (null = disabled)
        $maxBytes = config('akindutire-authorization.max_permission_size_bytes');
        $maxCount = config('akindutire-authorization.max_permission_count');

        // Skip validation if both limits are disabled
        if ($maxBytes === null && $maxCount === null) {
            return;
        }

        // Validate permission count
        if ($maxCount !== null && count($permissions) > $maxCount) {
            throw new \InvalidArgumentException(
                sprintf(
                    "Permission count (%d) exceeds maximum allowed (%d). " .
                    "Consider using permission namespacing (e.g., 'article.edit') or " .
                    "external permission storage for granular permissions. " .
                    "Configure via 'akindutire-authorization.max_permission_count'.",
                    count($permissions),
                    $maxCount
                )
            );
        }

        // Validate JSON size in bytes
        if ($maxBytes !== null) {
            $jsonSize = strlen(json_encode($permissions));

            if ($jsonSize > $maxBytes) {
                throw new \InvalidArgumentException(
                    sprintf(
                        "Permission JSON size (%d bytes) exceeds maximum allowed (%d bytes). " .
                        "Current permissions: %d items, average %d bytes/permission. " .
                        "Consider shorter permission names or external permission storage. " .
                        "Configure via 'akindutire-authorization.max_permission_size_bytes'.",
                        $jsonSize,
                        $maxBytes,
                        count($permissions),
                        count($permissions) > 0 ? (int)($jsonSize / count($permissions)) : 0
                    )
                );
            }
        }
    }

    /**
     * Boot the HasPermissions trait
     *
     * Registers model event listeners for automatic cache invalidation
     * when permission columns are modified
     */
    protected static function bootHasPermissions(): void
    {
        // Get column names from config
        $allowedColumn = config('akindutire-authorization.column_names.allowed_permissions', 'allowed_permissions');
        $revokedColumn = config('akindutire-authorization.column_names.revoked_permissions', 'revoked_permissions');

        // Listen for model updates
        static::updated(function ($model) use ($allowedColumn, $revokedColumn) {
            // Only clear cache if permission columns actually changed
            if ($model->wasChanged([$allowedColumn, $revokedColumn])) {
                // Clear entity cache (used by attributes for subject lookup)
                $entityCacheKey = sprintf(
                    'entity.%s.%s.%s',
                    str_replace('\\', '.', get_class($model)),
                    $model->getKeyName(),
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
    private function updatePermission(array $permissions = []): bool
    {
        // Get column name from config
        $allowedColumn = config('akindutire-authorization.column_names.allowed_permissions', 'allowed_permissions');
        $revokedColumn = config('akindutire-authorization.column_names.revoked_permissions', 'revoked_permissions');

        // Clean the permissions array:
        // - array_unique: remove duplicates
        // - array_filter: remove empty strings, null values
        // - array_values: re-index array (prevents JSON object conversion)

        $cleaned = array_values(array_filter(array_unique($permissions)));
      
        // Validate size before saving to prevent memory issues
        $this->validatePermissionSize($cleaned);

        // remove any revoked permissions that are now being allowed again
        $currentRevoked = $this->getRevokedPermissions();
        $updatedRevoked = array_diff($currentRevoked, $cleaned);

        // Use model attributes + save() to trigger Eloquent events (including cache invalidation)
        // Laravel automatically converts to JSON if column is cast as 'array'
        $this->{$allowedColumn} = $cleaned;
        $this->{$revokedColumn} = $updatedRevoked;

        return $this->save();
    }

    private function flatten(array $array): array
    {
        $result = [];
        foreach ($array as $item) {
            if (is_array($item)) {
                $result = array_merge($result, $this->flatten($item));
            } else {
                $result[] = $item;
            }
        }
        return $result;
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
    public function grantPermission(string|array $permission): bool
    {
        if (is_string($permission)) {
            // If an array is passed, delegate to updatePermission for batch processing
            $permission = [$permission];
        }

        // flatten the array in case of nested arrays (e.g., grantPermission(['can_edit', ['can_delete']]))
        $permission = $this->flatten($permission);

        // Validate that adding this permission won't exceed size limits
        // We check the projected size BEFORE the atomic operation
        $currentPermissions = $this->getAllowedPermissions();
        if (!in_array($permission, $currentPermissions, true)) {
            $projectedPermissions = array_merge($currentPermissions, [$permission]);
            $this->validatePermissionSize($projectedPermissions);
        }

        // Fallback: Use database transaction with row-level locking
        // lockForUpdate() prevents concurrent modifications to the same row
        return DB::transaction(function () use ($permission) {
            // SELECT ... FOR UPDATE locks the row until transaction completes
            $locked = static::where($this->getKeyName(), $this->getKey())
                ->lockForUpdate()
                ->first();

            $currentPermissions = [...$locked->getAllowedPermissions(), ...$permission];

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
    public function revokePermission(string|array $permission): bool
    {
        if (is_array($permission)) {
            // If an array is passed, delegate to revokePermissions for batch processing
            $permission = $this->flatten($permission);
            return $this->revokePermissions($permission);
        }
        // Get column name from config
        $revokedColumn = config('akindutire-authorization.column_names.revoked_permissions', 'revoked_permissions');

        // Fallback: Use database transaction with row-level locking
        return DB::transaction(function () use ($permission, $revokedColumn) {
            $locked = static::where($this->getKeyName(), $this->getKey())
                ->lockForUpdate()
                ->first();

            $currentRevoked = $locked->getRevokedPermissions();

            if (!in_array($permission, $currentRevoked, true)) {
                $currentRevoked[] = $permission;
            }

            // Will be auto-cast to JSON or CSV based on model configuration
            return $locked->update([
                $revokedColumn => $currentRevoked,
            ]);
        });
    }

    /**
     * Batch revoke multiple permissions at once
     *
     * Replaces the entire revoked_permissions column with the provided array.
     * This is more efficient than calling revokePermission() multiple times.
     *
     * Use this when you want to set a specific list of revoked permissions.
     * To add single permissions atomically, use revokePermission() instead.
     *
     * @param array $permissions Array of permission strings to revoke (e.g., ['can_edit', 'can_delete'])
     * @return bool True if update succeeded
     */
    private function revokePermissions(array $permissions = []): bool
    {
        // Get column name from config
        $revokedColumn = config('akindutire-authorization.column_names.revoked_permissions', 'revoked_permissions');

        // Clean the permissions array:
        // - array_unique: remove duplicates
        // - array_filter: remove empty strings, null values
        // - array_values: re-index array (prevents JSON object conversion)
        $cleaned = array_values(array_filter(array_unique($permissions)));

        // Laravel automatically converts to JSON if column is cast as 'array'
        // Otherwise, falls back to CSV string for backward compatibility
        return $this->where($this->getKeyName(), $this->getKey())->update([
            $revokedColumn => $cleaned,
        ]);
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
        // Get column name from config
        $allowedColumn = config('akindutire-authorization.column_names.allowed_permissions', 'allowed_permissions');

        $permissions = $this->$allowedColumn;
        
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
        // Get column name from config
        $revokedColumn = config('akindutire-authorization.column_names.revoked_permissions', 'revoked_permissions');

        $permissions = $this->$revokedColumn;

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
