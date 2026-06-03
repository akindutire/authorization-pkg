<?php
namespace Akindutire\Authorization\Attributes\Interfaces;

/**
 * Test helper class for permission checking
 *
 * Accepts permissions in various formats (array, JSON string, null, empty string)
 * and normalizes them to arrays, mimicking how Eloquent models handle JSON casts.
 */
class SubjectModel
{
    public readonly array $allowedPermissions;
    public readonly array $revokedPermissions;

    public function __construct(
        array|string|null $allowedPermissions,
        array|string|null $revokedPermissions,
    ) {
        $this->allowedPermissions = $this->normalizePermissions($allowedPermissions);
        $this->revokedPermissions = $this->normalizePermissions($revokedPermissions);
    }

    /**
     * Normalize permissions from various formats to array
     *
     * Handles:
     * - null → []
     * - '' → []
     * - '["can_view"]' → ["can_view"]
     * - ["can_view"] → ["can_view"]
     */
    private function normalizePermissions(array|string|null $permissions): array
    {
        if (is_null($permissions) || $permissions === '') {
            return [];
        }

        if (is_string($permissions)) {
            $decoded = json_decode($permissions, true);
            return is_array($decoded) ? $decoded : [];
        }

        return $permissions;
    }
}