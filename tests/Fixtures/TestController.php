<?php

namespace Akindutire\Authorization\Tests\Fixtures;

use Akindutire\Authorization\Attributes\HasAny;
use Akindutire\Authorization\Attributes\HasAll;
use Akindutire\Authorization\Attributes\SubjectValue;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class TestController
{
    #[HasAny(['can_view'], TestUser::class, 'id')]
    public function viewAction(#[SubjectValue('user_id')] Request $request): JsonResponse
    {
        return response()->json(['message' => 'View action authorized']);
    }

    #[HasAll(['can_edit', 'can_delete'], TestUser::class, 'id')]
    public function destructiveAction(#[SubjectValue('user_id')] Request $request): JsonResponse
    {
        return response()->json(['message' => 'Destructive action authorized']);
    }

    #[HasAny(['can_update'], TestTeamMember::class, 'id')]
    public function teamAction(#[SubjectValue('member_id')] Request $request): JsonResponse
    {
        return response()->json(['message' => 'Team action authorized']);
    }

    public function unprotectedAction(Request $request): JsonResponse
    {
        return response()->json(['message' => 'Unprotected action']);
    }
}
