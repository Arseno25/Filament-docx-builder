<?php

namespace Arseno25\DocxBuilder\Tests\Support\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

class TestUser extends Authenticatable
{
    protected $table = 'users';

    protected $guarded = [];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'permissions' => 'array',
    ];

    public function hasPermissionTo(string $permission): bool
    {
        $permissions = $this->permissions ?? [];

        return is_array($permissions) && in_array($permission, $permissions, true);
    }
}
