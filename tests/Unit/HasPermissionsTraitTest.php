<?php

namespace Akindutire\Authorization\Tests\Unit;

use Akindutire\Authorization\Tests\Fixtures\TestUser;
use Akindutire\Authorization\Tests\TestCase;

class HasPermissionsTraitTest extends TestCase
{
    /** @test */
    public function it_can_update_permissions()
    {
        $user = TestUser::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);

        $result = $user->updatePermission(['can_view', 'can_edit', 'can_delete']);

        $this->assertTrue($result);
        $this->assertEquals('can_view,can_edit,can_delete', $user->fresh()->allowed_permissions);
    }

    /** @test */
    public function it_can_grant_single_permission()
    {
        $user = TestUser::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'allowed_permissions' => 'can_view',
        ]);

        $user->grantPermission('can_edit');

        $this->assertEquals('can_view,can_edit', $user->fresh()->allowed_permissions);
    }

    /** @test */
    public function it_does_not_duplicate_permissions_when_granting()
    {
        $user = TestUser::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'allowed_permissions' => 'can_view,can_edit',
        ]);

        $user->grantPermission('can_view');

        $this->assertEquals('can_view,can_edit', $user->fresh()->allowed_permissions);
    }

    /** @test */
    public function it_can_revoke_permission()
    {
        $user = TestUser::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'allowed_permissions' => 'can_view,can_edit,can_delete',
        ]);

        $user->revokePermission('can_delete');

        $this->assertEquals('can_delete', $user->fresh()->revoked_permissions);
    }

    /** @test */
    public function it_does_not_duplicate_revoked_permissions()
    {
        $user = TestUser::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'revoked_permissions' => 'can_delete',
        ]);

        $user->revokePermission('can_delete');

        $this->assertEquals('can_delete', $user->fresh()->revoked_permissions);
    }

    /** @test */
    public function it_gets_allowed_permissions_as_array()
    {
        $user = TestUser::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'allowed_permissions' => 'can_view,can_edit,can_delete',
        ]);

        $permissions = $user->getAllowedPermissions();

        $this->assertIsArray($permissions);
        $this->assertCount(3, $permissions);
        $this->assertContains('can_view', $permissions);
        $this->assertContains('can_edit', $permissions);
        $this->assertContains('can_delete', $permissions);
    }

    /** @test */
    public function it_gets_revoked_permissions_as_array()
    {
        $user = TestUser::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'revoked_permissions' => 'can_delete,can_admin',
        ]);

        $permissions = $user->getRevokedPermissions();

        $this->assertIsArray($permissions);
        $this->assertCount(2, $permissions);
        $this->assertContains('can_delete', $permissions);
        $this->assertContains('can_admin', $permissions);
    }

    /** @test */
    public function it_gets_effective_permissions()
    {
        $user = TestUser::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'allowed_permissions' => 'can_view,can_edit,can_delete',
            'revoked_permissions' => 'can_delete',
        ]);

        $effective = $user->getEffectivePermissions();

        $this->assertIsArray($effective);
        $this->assertCount(2, $effective);
        $this->assertContains('can_view', $effective);
        $this->assertContains('can_edit', $effective);
        $this->assertNotContains('can_delete', $effective);
    }

    /** @test */
    public function it_checks_if_has_permission()
    {
        $user = TestUser::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'allowed_permissions' => 'can_view,can_edit',
        ]);

        $this->assertTrue($user->hasPermission('can_view'));
        $this->assertTrue($user->hasPermission('can_edit'));
        $this->assertFalse($user->hasPermission('can_delete'));
    }

    /** @test */
    public function it_checks_if_has_any_permission()
    {
        $user = TestUser::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'allowed_permissions' => 'can_view,can_edit',
        ]);

        $this->assertTrue($user->hasAnyPermission(['can_view', 'can_delete']));
        $this->assertFalse($user->hasAnyPermission(['can_delete', 'can_admin']));
    }

    /** @test */
    public function it_checks_if_has_all_permissions()
    {
        $user = TestUser::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'allowed_permissions' => 'can_view,can_edit,can_delete',
        ]);

        $this->assertTrue($user->hasAllPermissions(['can_view', 'can_edit']));
        $this->assertFalse($user->hasAllPermissions(['can_view', 'can_admin']));
    }

    /** @test */
    public function it_handles_null_permissions_in_trait_methods()
    {
        $user = TestUser::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'allowed_permissions' => null,
        ]);

        $this->assertIsArray($user->getAllowedPermissions());
        $this->assertEmpty($user->getAllowedPermissions());
        $this->assertFalse($user->hasPermission('can_view'));
    }

    /** @test */
    public function it_sets_permissions_from_role()
    {
        $user = TestUser::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);

        $result = $user->setPermissionsFromRole('owner');

        $this->assertTrue($result);
        $permissions = $user->fresh()->getAllowedPermissions();
        $this->assertContains('can_update', $permissions);
        $this->assertContains('can_delete', $permissions);
    }

    /** @test */
    public function it_filters_empty_values_when_updating_permissions()
    {
        $user = TestUser::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);

        $user->updatePermission(['can_view', '', 'can_edit', null]);

        $this->assertEquals('can_view,can_edit', $user->fresh()->allowed_permissions);
    }
}
