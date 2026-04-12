<?php

namespace Akindutire\Authorization\Tests\Fixtures;

use Akindutire\Authorization\Traits\HasPermissions;
use Illuminate\Database\Eloquent\Model;

class TestUser extends Model
{
    use HasPermissions;

    protected $table = 'test_users';

    protected $fillable = [
        'name',
        'email',
        'allowed_permissions',
        'revoked_permissions',
    ];
}
