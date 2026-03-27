<?php

namespace Arseno25\DocxBuilder\Policies\Concerns;

trait ChecksDocxBuilderPermissions
{
    protected function hasPermission(mixed $user, string $permission): bool
    {
        if (! $user) {
            return false;
        }

        if (method_exists($user, 'hasPermissionTo')) {
            return (bool) $user->hasPermissionTo($permission);
        }

        if (method_exists($user, 'can')) {
            return (bool) $user->can($permission);
        }

        return false;
    }
}
