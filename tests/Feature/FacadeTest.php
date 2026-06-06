<?php

namespace Akindutire\Authorization\Tests\Feature;

use Akindutire\Authorization\Facades\EntityPermission;
use Akindutire\Authorization\Tests\Fixtures\TestUser;
use Akindutire\Authorization\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class FacadeTest extends TestCase
{
    /** @test */
    #[Test]
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
    #[Test]
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
    #[Test]
    public function it_can_get_abilities_via_facade()
    {
        $permissions = EntityPermission::getAbilities('owner');

        $this->assertIsArray($permissions);
        $this->assertNotEmpty($permissions);
    }

    /** @test */
    #[Test]
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
