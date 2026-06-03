<?php

namespace Akindutire\Authorization\Tests\Fixtures;

use Akindutire\Authorization\Attributes\HasAny;
use Akindutire\Authorization\Attributes\HasAll;
use Akindutire\Authorization\Attributes\SubjectValue;

/**
 * Test controller with various attribute configurations
 * Used to test ReflectionCacheKeyGenerator hash generation
 */
class TestControllerWithAttributes
{
    #[HasAny(['can_view', 'can_edit'], TestUser::class, 'id')]
    public function methodWithHashAny(#[SubjectValue('user_id')] int $userId)
    {
        // Test method with HasAny attribute
    }

    #[HasAll(['can_view', 'can_edit'], TestUser::class, 'id')]
    public function methodWithHashAll(#[SubjectValue('user_id')] int $userId)
    {
        // Test method with HasAll attribute
    }

    #[HasAny(['can_delete', 'can_admin'], TestUser::class, 'id')]
    public function methodWithDifferentActions(#[SubjectValue('user_id')] int $userId)
    {
        // Same attribute type, different actions
    }

    #[HasAny(['can_view', 'can_edit'], TestTeamMember::class, 'id')]
    public function methodWithDifferentSubject(#[SubjectValue('member_id')] int $memberId)
    {
        // Same actions, different subject class
    }

    #[HasAny(['can_view', 'can_edit'], TestUser::class, 'id')]
    public function methodWithDifferentSubjectValue(#[SubjectValue('different_user_id')] int $differentUserId)
    {
        // Same actions and subject, different SubjectValue parameter
    }

    #[HasAny([['can_view', 'can_edit'], ['can_admin']], TestUser::class, 'id')]
    public function methodWithNestedArrayActions(#[SubjectValue('user_id')] int $userId)
    {
        // Nested array in actions
    }

    public function methodWithoutAttributes(int $userId)
    {
        // No attributes - should generate 'none' hash
    }
}
