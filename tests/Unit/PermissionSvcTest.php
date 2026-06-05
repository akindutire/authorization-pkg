<?php

namespace Akindutire\Authorization\Tests\Unit;

use Akindutire\Authorization\Attributes\Interfaces\SubjectModel;
use Akindutire\Authorization\Services\PermissionSvc;
use Akindutire\Authorization\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class PermissionSvcTest extends TestCase
{
    protected PermissionSvc $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PermissionSvc();
    }

    #[Test]
    public function it_can_set_subject()
    {
        $subject = new SubjectModel(['can_view', 'can_edit'], []);

        $result = $this->service->subject($subject);

        $this->assertInstanceOf(PermissionSvc::class, $result);
    }

    #[Test]
    public function it_returns_true_when_subject_has_any_permission()
    {
        $subject = new SubjectModel(['can_view', 'can_edit', 'can_delete'], []);

        $result = $this->service->subject($subject)->hasAny(['can_edit', 'can_unknown']);

        $this->assertTrue($result);
    }

    #[Test]
    public function it_returns_false_when_subject_has_none_of_permissions()
    {
        $subject = new SubjectModel(['can_view'], []);

        $result = $this->service->subject($subject)->hasAny(['can_edit', 'can_delete']);

        $this->assertFalse($result);
    }

    #[Test]
    public function it_returns_true_when_subject_has_all_permissions()
    {
        $subject = new SubjectModel(['can_view', 'can_edit', 'can_delete'], []);

        $result = $this->service->subject($subject)->hasAll(['can_view', 'can_edit']);

        $this->assertTrue($result);
    }

    #[Test]
    public function it_returns_false_when_subject_missing_one_permission()
    {
        $subject = new SubjectModel(['can_view', 'can_edit'], []);

        $result = $this->service->subject($subject)->hasAll(['can_view', 'can_edit', 'can_delete']);

        $this->assertFalse($result);
    }

    #[Test]
    public function it_excludes_revoked_permissions_from_check()
    {
        $subject = new SubjectModel(['can_view', 'can_edit', 'can_delete'], ['can_delete']);

        $hasDelete = $this->service->subject($subject)->hasAny(['can_delete']);
        $hasView = $this->service->subject($subject)->hasAny(['can_view']);

        $this->assertFalse($hasDelete);
        $this->assertTrue($hasView);
    }

    #[Test]
    public function it_throws_exception_when_checking_permissions_without_subject()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("A subject is required");

        $this->service->hasAny(['can_view']);
    }

    #[Test]
    public function it_returns_false_for_empty_permission_array()
    {
        $subject = new SubjectModel(['can_view'], []);

        $result = $this->service->subject($subject)->hasAny([]);

        $this->assertFalse($result);
    }

    #[Test]
    public function it_handles_flattened_nested_permission_arrays()
    {
        $subject = new SubjectModel(['can_view', 'can_edit'], []);

        $result = $this->service->subject($subject)->hasAny([
            ['can_view'],
            ['can_delete']
        ]);

        $this->assertTrue($result);
    }

    #[Test]
    public function it_gets_abilities_for_role()
    {
        config(['akindutire-authorization.abilities' => [
            'owner' => ['can_update', 'can_delete', 'can_invite'],
            'admin' => ['can_update', 'can_invite'],
            'member' => ['can_view'],
        ]]);

        $ownerPermissions = $this->service->getAbilities('owner');
        $adminPermissions = $this->service->getAbilities('admin');

        $this->assertIsArray($ownerPermissions);
        $this->assertContains('can_update', $ownerPermissions);
        $this->assertContains('can_delete', $ownerPermissions);
        $this->assertContains('can_invite', $ownerPermissions);

        $this->assertIsArray($adminPermissions);
        $this->assertContains('can_update', $adminPermissions);
        $this->assertNotContains('can_delete', $adminPermissions);
    }

    #[Test]
    public function it_returns_empty_array_for_unknown_role()
    {
        config(['akindutire-authorization.abilities' => [
            'owner' => ['can_update', 'can_delete'],
        ]]);

        $permissions = $this->service->getAbilities('unknown_role');

        $this->assertIsArray($permissions);
        $this->assertEmpty($permissions);
    }

    #[Test]
    public function it_handles_simple_abilities_array()
    {
        config(['akindutire-authorization.abilities' => [
            'can_view',
            'can_edit',
            'can_delete',
        ]]);

        // When abilities is a simple array, role lookup returns empty
        $permissions = $this->service->getAbilities('owner');

        $this->assertIsArray($permissions);
    }

    #[Test]
    public function it_handles_null_permissions_gracefully()
    {
        $subject = new SubjectModel(null, null);

        $result = $this->service->subject($subject)->hasAny(['can_view']);

        $this->assertFalse($result);
    }

    #[Test]
    public function it_handles_empty_string_permissions()
    {
        $subject = new SubjectModel('', '');

        $result = $this->service->subject($subject)->hasAny(['can_view']);

        $this->assertFalse($result);
    }

    #[Test]
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

    #[Test]
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

    #[Test]
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

    #[Test]
    public function it_handles_whitespace_in_permissions()
    {
        $subject = new SubjectModel(['can_view', '  ', 'can_edit', ''], []);

        $result = $this->service->subject($subject)->hasAny(['can_view']);

        $this->assertTrue($result);
    }

    #[Test]
    public function it_throws_exception_if_abilities_config_is_not_array()
    {
        config(['akindutire-authorization.abilities.owner' => 'not-an-array']);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("Default abilities must be an array");

        $this->service->getAbilities('owner');
    }
}
