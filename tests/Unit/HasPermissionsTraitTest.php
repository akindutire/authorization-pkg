<?php

namespace Akindutire\Authorization\Tests\Unit;

use Akindutire\Authorization\Tests\Fixtures\TestUser;
use Akindutire\Authorization\Tests\TestCase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;

class HasPermissionsTraitTest extends TestCase
{
    #[Test]
    public function it_auto_initializes_casts_for_permission_columns()
    {
        $user = new TestUser();

        $casts = $user->getCasts();

        $this->assertArrayHasKey('allowed_permissions', $casts);
        $this->assertArrayHasKey('revoked_permissions', $casts);
        $this->assertEquals('array', $casts['allowed_permissions']);
        $this->assertEquals('array', $casts['revoked_permissions']);
    }

    #[Test]
    public function it_can_grant_single_permission()
    {
        $user = TestUser::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'allowed_permissions' => ['can_view'],
        ]);

        $result = $user->grantPermission('can_edit');

        $this->assertTrue($result);
        $permissions = $user->fresh()->getAllowedPermissions();
        $this->assertContains('can_view', $permissions);
        $this->assertContains('can_edit', $permissions);
    }

    #[Test]
    public function it_can_grant_multiple_permissions_at_once()
    {
        $user = TestUser::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'allowed_permissions' => ['can_view'],
        ]);

        $result = $user->grantPermission(['can_edit', 'can_delete']);

        $this->assertTrue($result);
        $permissions = $user->fresh()->getAllowedPermissions();
        $this->assertContains('can_view', $permissions);
        $this->assertContains('can_edit', $permissions);
        $this->assertContains('can_delete', $permissions);
    }

    #[Test]
    public function it_does_not_duplicate_permissions_when_granting()
    {
        $user = TestUser::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'allowed_permissions' => ['can_view', 'can_edit'],
        ]);

        $user->grantPermission('can_view');

        $permissions = $user->fresh()->getAllowedPermissions();
        $this->assertCount(2, $permissions);
        $this->assertEquals(['can_view', 'can_edit'], $permissions);
    }

    #[Test]
    public function it_can_revoke_single_permission()
    {
        $user = TestUser::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'allowed_permissions' => ['can_view', 'can_edit', 'can_delete'],
        ]);

        $result = $user->revokePermission('can_delete');

        $this->assertTrue($result);
        $revoked = $user->fresh()->getRevokedPermissions();
        $this->assertContains('can_delete', $revoked);
    }

    #[Test]
    public function it_can_revoke_multiple_permissions()
    {
        $user = TestUser::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'allowed_permissions' => ['can_view', 'can_edit', 'can_delete'],
        ]);

        $result = $user->revokePermission(['can_delete', 'can_edit']);

        $this->assertTrue($result);
        $revoked = $user->fresh()->getRevokedPermissions();
        $this->assertContains('can_delete', $revoked);
        $this->assertContains('can_edit', $revoked);
    }

    #[Test]
    public function it_does_not_duplicate_revoked_permissions()
    {
        $user = TestUser::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'revoked_permissions' => ['can_delete'],
        ]);

        $user->revokePermission('can_delete');

        $revoked = $user->fresh()->getRevokedPermissions();
        $this->assertCount(1, $revoked);
        $this->assertEquals(['can_delete'], $revoked);
    }

    #[Test]
    public function it_stores_permissions_as_json_array()
    {
        $user = TestUser::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);

        $user->grantPermission(['can_view', 'can_edit', 'can_delete']);

        $fresh = $user->fresh();

        // Check database storage is JSON
        $rawValue = \DB::table('test_users')
            ->where('id', $user->id)
            ->value('allowed_permissions');

        $this->assertJson($rawValue);

        // Check it's properly decoded
        $this->assertIsArray($fresh->getAllowedPermissions());
        $this->assertCount(3, $fresh->getAllowedPermissions());
    }

    #[Test]
    public function it_gets_allowed_permissions_as_array()
    {
        $user = TestUser::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'allowed_permissions' => ['can_view', 'can_edit', 'can_delete'],
        ]);

        $permissions = $user->getAllowedPermissions();

        $this->assertIsArray($permissions);
        $this->assertCount(3, $permissions);
        $this->assertContains('can_view', $permissions);
        $this->assertContains('can_edit', $permissions);
        $this->assertContains('can_delete', $permissions);
    }

    #[Test]
    public function it_gets_revoked_permissions_as_array()
    {
        $user = TestUser::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'revoked_permissions' => ['can_delete', 'can_admin'],
        ]);

        $permissions = $user->getRevokedPermissions();

        $this->assertIsArray($permissions);
        $this->assertCount(2, $permissions);
        $this->assertContains('can_delete', $permissions);
        $this->assertContains('can_admin', $permissions);
    }

    #[Test]
    public function it_gets_effective_permissions()
    {
        $user = TestUser::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'allowed_permissions' => ['can_view', 'can_edit', 'can_delete'],
            'revoked_permissions' => ['can_delete'],
        ]);

        $effective = $user->getEffectivePermissions();

        $this->assertIsArray($effective);
        $this->assertCount(2, array_values($effective));
        $this->assertContains('can_view', $effective);
        $this->assertContains('can_edit', $effective);
        $this->assertNotContains('can_delete', $effective);
    }

    #[Test]
    public function it_checks_if_has_permission()
    {
        $user = TestUser::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'allowed_permissions' => ['can_view', 'can_edit'],
        ]);

        $this->assertTrue($user->hasPermission('can_view'));
        $this->assertTrue($user->hasPermission('can_edit'));
        $this->assertFalse($user->hasPermission('can_delete'));
    }

    #[Test]
    public function it_checks_if_has_any_permission()
    {
        $user = TestUser::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'allowed_permissions' => ['can_view', 'can_edit'],
        ]);

        $this->assertTrue($user->hasAnyPermission(['can_view', 'can_delete']));
        $this->assertFalse($user->hasAnyPermission(['can_delete', 'can_admin']));
    }

    #[Test]
    public function it_checks_if_has_all_permissions()
    {
        $user = TestUser::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'allowed_permissions' => ['can_view', 'can_edit', 'can_delete'],
        ]);

        $this->assertTrue($user->hasAllPermissions(['can_view', 'can_edit']));
        $this->assertFalse($user->hasAllPermissions(['can_view', 'can_admin']));
    }

    #[Test]
    public function it_handles_null_permissions_gracefully()
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

    #[Test]
    public function it_handles_empty_array_permissions()
    {
        $user = TestUser::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'allowed_permissions' => [],
        ]);

        $this->assertIsArray($user->getAllowedPermissions());
        $this->assertEmpty($user->getAllowedPermissions());
        $this->assertFalse($user->hasPermission('can_view'));
    }

    #[Test]
    public function it_filters_empty_values_when_granting_permissions()
    {
        $user = TestUser::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);

        $user->grantPermission(['can_view', '', 'can_edit', null]);

        $permissions = $user->fresh()->getAllowedPermissions();
        $this->assertCount(2, $permissions);
        $this->assertContains('can_view', $permissions);
        $this->assertContains('can_edit', $permissions);
    }

    #[Test]
    public function it_handles_nested_permission_arrays()
    {
        $user = TestUser::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);

        $user->grantPermission([['can_view'], ['can_edit', 'can_delete']]);

        $permissions = $user->fresh()->getAllowedPermissions();
        $this->assertContains('can_view', $permissions);
        $this->assertContains('can_edit', $permissions);
        $this->assertContains('can_delete', $permissions);
    }

    #[Test]
    public function it_invalidates_cache_when_permissions_change()
    {
        $user = TestUser::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'allowed_permissions' => ['can_view'],
        ]);

        // Simulate cache
        $cacheKey = sprintf(
            'entity.%s.%s.%s',
            str_replace('\\', '.', get_class($user)),
            'id',
            $user->id
        );
        Cache::put($cacheKey, ['a' => ['can_view'], 'r' => []], 300);

        // Verify cache exists
        $this->assertTrue(Cache::has($cacheKey));

        // Grant permission (should trigger cache invalidation)
        $user->grantPermission('can_edit');

        // Cache should be cleared
        $this->assertFalse(Cache::has($cacheKey));
    }

    #[Test]
    public function revoke_permission_removes_from_allowed_if_present()
    {
        $user = TestUser::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'allowed_permissions' => ['can_view', 'can_edit', 'can_delete'],
        ]);

        $user->revokePermission('can_delete');

        $effective = $user->fresh()->getEffectivePermissions();
        $this->assertNotContains('can_delete', $effective);
    }

    #[Test]
    public function grant_permission_removes_from_revoked_if_present()
    {
        $user = TestUser::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'allowed_permissions' => ['can_view', 'can_edit'],
            'revoked_permissions' => ['can_delete'],
        ]);

        $user->grantPermission(['can_delete']);

        $fresh = $user->fresh();
        $this->assertContains('can_delete', $fresh->getAllowedPermissions());
        $this->assertNotContains('can_delete', $fresh->getRevokedPermissions());
    }
}
