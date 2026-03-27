<?php

use Arseno25\DocxBuilder\Tests\TestCase;
use Arseno25\DocxBuilder\Tests\Support\Models\TestUser;

uses(TestCase::class)->in(__DIR__);

function loginWithPermissions(array $permissions): TestUser
{
    /** @var TestUser $user */
    $user = TestUser::create([
        'name' => 'Test User',
        'email' => uniqid('test_', true) . '@example.com',
        'password' => bcrypt('password'),
        'permissions' => $permissions,
    ]);

    test()->actingAs($user);

    return $user;
}
