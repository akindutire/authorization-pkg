<?php

namespace Akindutire\Authorization\Facades;

use Illuminate\Support\Facades\Facade;
use Akindutire\Authorization\Services\PermissionSvc;

/**
 * Facade for accessing PermissionSvc
 *
 * @method static \Akindutire\Authorization\Services\PermissionSvc subject(\Illuminate\Database\Eloquent\Model $subject, string $roleAllowedLookupIndex = 'allowed_permissions', string $subjectRevokedLookupIndex = 'revoked_permissions')
 * @method static bool hasAny(array $actions)
 * @method static bool hasAll(array $actions)
 * @method static array getAbilities(string $role)
 *
 * @see \Akindutire\Authorization\Services\PermissionSvc
 */
class EntityPermission extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return PermissionSvc::class;
    }
}
