<?php

namespace Akindutire\Authorization\Tests\Unit;

use Akindutire\Authorization\Services\PermissionSvc;
use Akindutire\Authorization\Tests\Fixtures\TestUser;
use Akindutire\Authorization\Tests\TestCase;

class PermissionSvcTest extends TestCase
{
    protected PermissionSvc $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PermissionSvc();
    }

    /** @test */
    public function it_can_set_subject()
    {
        $user = TestUser::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'allowed_permissions' => 'can_view,can_edit',
        ]);

        $result = $this->service->subject($user);

        $this->assertInstanceOf(PermissionSvc::class, $result);
    }

    /** @test */
    public function it_throws_exception_when_subject_missing_allowed_permissions_column()
    {
        $user = new class extends \Illuminate\Database\Eloquent\Model {
            protected $fillable = [];
        };

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("'allowed_permissions' is required");

        $this->service->subject($user);
    }

    /** @test */
    public function it_throws_exception_when_subject_missing_revoked_permissions_column()
    {
        $user = new class extends \Illuminate\Database\Eloquent\Model {
            protected $attributes = ['allowed_permissions' => ''];
        };

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("'revoked_permissions' is required");

        $this->service->subject($user);
    }

    /** @test */
    public function it_returns_true_when_subject_has_any_permission()
    {
        $user = TestUser::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'allowed_permissions' => 'can_view,can_edit,can_delete',
        ]);

        $result = $this->service->subject($user)->hasAny(['can_edit', 'can_unknown']);

        $this->assertTrue($result);
    }

    /** @test */
    public function it_returns_false_when_subject_has_none_of_permissions()
    {
        $user = TestUser::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'allowed_permissions' => 'can_view',
        ]);

        $result = $this->service->subject($user)->hasAny(['can_edit', 'can_delete']);

        $this->assertFalse($result);
    }

    /** @test */
    public function it_returns_true_when_subject_has_all_permissions()
    {
        $user = TestUser::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'allowed_permissions' => 'can_view,can_edit,can_delete',
        ]);

        $result = $this->service->subject($user)->hasAll(['can_view', 'can_edit']);

        $this->assertTrue($result);
    }

    /** @test */
    public function it_returns_false_when_subject_missing_one_permission()
    {
        $user = TestUser::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'allowed_permissions' => 'can_view,can_edit',
        ]);

        $result = $this->service->subject($user)->hasAll(['can_view', 'can_edit', 'can_delete']);

        $this->assertFalse($result);
    }

    /** @test */
    public function it_excludes_revoked_permissions_from_check()
    {
        $user = TestUser::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'allowed_permissions' => 'can_view,can_edit,can_delete',
            'revoked_permissions' => 'can_delete',
        ]);

        $hasDelete = $this->service->subject($user)->hasAny(['can_delete']);
        $hasView = $this->service->subject($user)->hasAny(['can_view']);

        $this->assertFalse($hasDelete);
        $this->assertTrue($hasView);
    }

    /** @test */
    public function it_throws_exception_when_checking_permissions_without_subject()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("A subject is required");

        $this->service->hasAny(['can_view']);
    }

    /** @test */
    public function it_returns_false_for_empty_permission_array()
    {
        $user = TestUser::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'allowed_permissions' => 'can_view',
        ]);

        $result = $this->service->subject($user)->hasAny([]);

        $this->assertFalse($result);
    }

    /** @test */
    public function it_handles_flattened_nested_permission_arrays()
    {
        $user = TestUser::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'allowed_permissions' => 'can_view,can_edit',
        ]);

        $result = $this->service->subject($user)->hasAny([
            ['can_view'],
            ['can_delete']
        ]);

        $this->assertTrue($result);
    }

    /** @test */
    public function it_gets_default_actions_for_role()
    {
        $permissions = $this->service->getDefaultActions('owner');

        $this->assertIsArray($permissions);
        $this->assertContains('can_update', $permissions);
        $this->assertContains('can_delete', $permissions);
    }

    /** @test */
    public function it_returns_all_actions_for_unknown_role()
    {
        $permissions = $this->service->getDefaultActions('unknown_role');

        $this->assertIsArray($permissions);
    }

    /** @test */
    public function it_handles_null_permissions_gracefully()
    {
        $user = TestUser::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'allowed_permissions' => null,
            'revoked_permissions' => null,
        ]);

        $result = $this->service->subject($user)->hasAny(['can_view']);

        $this->assertFalse($result);
    }
}
