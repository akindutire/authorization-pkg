<?php

namespace Akindutire\Authorization\Tests\Unit;

use Akindutire\Authorization\Attributes\Interfaces\SubjectModel;
use Akindutire\Authorization\Services\PermissionSvc;
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
        $subject = new SubjectModel(['can_view', 'can_edit'], []);

        $result = $this->service->subject($subject);

        $this->assertInstanceOf(PermissionSvc::class, $result);
    }

    /** @test */
    public function it_returns_true_when_subject_has_any_permission()
    {
        $subject = new SubjectModel(['can_view', 'can_edit', 'can_delete'], []);

        $result = $this->service->subject($subject)->hasAny(['can_edit', 'can_unknown']);

        $this->assertTrue($result);
    }

    /** @test */
    public function it_returns_false_when_subject_has_none_of_permissions()
    {
        $subject = new SubjectModel(['can_view'], []);

        $result = $this->service->subject($subject)->hasAny(['can_edit', 'can_delete']);

        $this->assertFalse($result);
    }

    /** @test */
    public function it_returns_true_when_subject_has_all_permissions()
    {
        $subject = new SubjectModel(['can_view', 'can_edit', 'can_delete'], []);

        $result = $this->service->subject($subject)->hasAll(['can_view', 'can_edit']);

        $this->assertTrue($result);
    }

    /** @test */
    public function it_returns_false_when_subject_missing_one_permission()
    {
        $subject = new SubjectModel(['can_view', 'can_edit'], []);

        $result = $this->service->subject($subject)->hasAll(['can_view', 'can_edit', 'can_delete']);

        $this->assertFalse($result);
    }

    /** @test */
    public function it_excludes_revoked_permissions_from_check()
    {
        $subject = new SubjectModel(['can_view', 'can_edit', 'can_delete'], ['can_delete']);

        $hasDelete = $this->service->subject($subject)->hasAny(['can_delete']);
        $hasView = $this->service->subject($subject)->hasAny(['can_view']);

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
        $subject = new SubjectModel(['can_view'], []);

        $result = $this->service->subject($subject)->hasAny([]);

        $this->assertFalse($result);
    }

    /** @test */
    public function it_handles_flattened_nested_permission_arrays()
    {
        $subject = new SubjectModel(['can_view', 'can_edit'], []);

        $result = $this->service->subject($subject)->hasAny([
            ['can_view'],
            ['can_delete']
        ]);

        $this->assertTrue($result);
    }

    /** @test */
    public function it_gets_default_actions_for_role()
    {
        config(['akindutire-authorization.abilities' => [
            'owner' => ['can_update', 'can_delete', 'can_invite'],
            'admin' => ['can_update', 'can_invite'],
            'member' => ['can_view'],
        ]]);

        $ownerPermissions = $this->service->getDefaultActions('owner');
        $adminPermissions = $this->service->getDefaultActions('admin');

        $this->assertIsArray($ownerPermissions);
        $this->assertContains('can_update', $ownerPermissions);
        $this->assertContains('can_delete', $ownerPermissions);
        $this->assertContains('can_invite', $ownerPermissions);

        $this->assertIsArray($adminPermissions);
        $this->assertContains('can_update', $adminPermissions);
        $this->assertNotContains('can_delete', $adminPermissions);
    }

    /** @test */
    public function it_returns_empty_array_for_unknown_role()
    {
        config(['akindutire-authorization.abilities' => [
            'owner' => ['can_update', 'can_delete'],
        ]]);

        $permissions = $this->service->getDefaultActions('unknown_role');

        $this->assertIsArray($permissions);
        $this->assertEmpty($permissions);
    }

    /** @test */
    public function it_handles_simple_abilities_array()
    {
        config(['akindutire-authorization.abilities' => [
            'can_view',
            'can_edit',
            'can_delete',
        ]]);

        // When abilities is a simple array, role lookup returns empty
        $permissions = $this->service->getDefaultActions('owner');

        $this->assertIsArray($permissions);
    }

    /** @test */
    public function it_handles_null_permissions_gracefully()
    {
        $subject = new SubjectModel(null, null);

        $result = $this->service->subject($subject)->hasAny(['can_view']);

        $this->assertFalse($result);
    }

    /** @test */
    public function it_handles_empty_string_permissions()
    {
        $subject = new SubjectModel('', '');

        $result = $this->service->subject($subject)->hasAny(['can_view']);

        $this->assertFalse($result);
    }

    /** @test */
    public function it_handles_json_string_permissions()
    {
        $subject = new SubjectModel(
            '["can_view", "can_edit"]',
            '["can_delete"]'
        );

        $hasView = $this->service->subject($subject)->hasAny(['can_view']);
        $hasDelete = $this->service->subject($subject)->hasAny(['can_delete']);

        $this->assertTrue($hasView);
        $this->assertFalse($hasDelete); // Revoked
    }

    /** @test */
    public function it_clears_memoization_when_subject_changes()
    {
        $subject1 = new SubjectModel(['can_view'], []);
        $subject2 = new SubjectModel(['can_edit'], []);

        // Check with first subject
        $this->assertTrue($this->service->subject($subject1)->hasAny(['can_view']));
        $this->assertFalse($this->service->subject($subject1)->hasAny(['can_edit']));

        // Change subject
        $this->assertFalse($this->service->subject($subject2)->hasAny(['can_view']));
        $this->assertTrue($this->service->subject($subject2)->hasAny(['can_edit']));
    }

    /** @test */
    public function it_memoizes_permission_resolution_for_same_subject()
    {
        $subject = new SubjectModel(['can_view', 'can_edit', 'can_delete'], ['can_delete']);

        // First call resolves permissions
        $result1 = $this->service->subject($subject)->hasAny(['can_view']);

        // Second call should use memoized result (same subject instance)
        $result2 = $this->service->hasAll(['can_view', 'can_edit']);

        $this->assertTrue($result1);
        $this->assertTrue($result2);
    }

    /** @test */
    public function it_handles_whitespace_in_permissions()
    {
        $subject = new SubjectModel(['can_view', '  ', 'can_edit', ''], []);

        $result = $this->service->subject($subject)->hasAny(['can_view']);

        $this->assertTrue($result);
    }

    /** @test */
    public function it_throws_exception_if_abilities_config_is_not_array()
    {
        config(['akindutire-authorization.abilities.owner' => 'not-an-array']);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("Default abilities must be an array");

        $this->service->getDefaultActions('owner');
    }
}
