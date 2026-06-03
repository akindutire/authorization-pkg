<?php

namespace Akindutire\Authorization\Tests\Feature;

use Akindutire\Authorization\Facades\EntityPermission;
use Akindutire\Authorization\Tests\Fixtures\TestUser;
use Akindutire\Authorization\Tests\TestCase;

class FacadeTest extends TestCase
{
    /** @test */
    public function it_can_check_permissions_via_facade()
    {
        $user = TestUser::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'allowed_permissions' => ['can_view', 'can_edit'],
        ]);

        $hasView = EntityPermission::subject($user)->hasAny(['can_view']);
        $hasDelete = EntityPermission::subject($user)->hasAny(['can_delete']);

        $this->assertTrue($hasView);
        $this->assertFalse($hasDelete);
    }

    /** @test */
    public function it_can_check_all_permissions_via_facade()
    {
        $user = TestUser::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'allowed_permissions' => ['can_view', 'can_edit', 'can_delete'],
        ]);

        $hasAll = EntityPermission::subject($user)->hasAll(['can_view', 'can_edit']);
        $hasMissing = EntityPermission::subject($user)->hasAll(['can_view', 'can_admin']);

        $this->assertTrue($hasAll);
        $this->assertFalse($hasMissing);
    }

    /** @test */
    public function it_can_get_default_actions_via_facade()
    {
        $permissions = EntityPermission::getDefaultActions('owner');

        $this->assertIsArray($permissions);
        $this->assertNotEmpty($permissions);
    }

    /** @test */
    public function facade_respects_revoked_permissions()
    {
        $user = TestUser::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'allowed_permissions' => ['can_view', 'can_edit', 'can_delete'],
            'revoked_permissions' => ['can_delete'],
        ]);

        $hasDelete = EntityPermission::subject($user)->hasAny(['can_delete']);
        $hasView = EntityPermission::subject($user)->hasAny(['can_view']);

        $this->assertFalse($hasDelete);
        $this->assertTrue($hasView);
    }
}
