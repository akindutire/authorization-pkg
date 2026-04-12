<?php

namespace Akindutire\Authorization\Tests\Fixtures;

use Akindutire\Authorization\Traits\HasPermissions;
use Illuminate\Database\Eloquent\Model;

class TestTeamMember extends Model
{
    use HasPermissions;

    protected $table = 'test_team_members';

    protected $fillable = [
        'user_id',
        'role',
        'allowed_permissions',
        'revoked_permissions',
    ];
}
