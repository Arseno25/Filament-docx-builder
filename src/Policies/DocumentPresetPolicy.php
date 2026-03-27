<?php

namespace Arseno25\DocxBuilder\Policies;

use Arseno25\DocxBuilder\Models\DocumentPreset;
use Arseno25\DocxBuilder\Policies\Concerns\ChecksDocxBuilderPermissions;
use Arseno25\DocxBuilder\Support\DocxBuilderPermissions;

class DocumentPresetPolicy
{
    use ChecksDocxBuilderPermissions;

    public function viewAny(mixed $user): bool
    {
        return $this->hasPermission($user, DocxBuilderPermissions::PRESETS_VIEW_ANY);
    }

    public function view(mixed $user, DocumentPreset $preset): bool
    {
        return $this->viewAny($user);
    }

    public function create(mixed $user): bool
    {
        return $this->hasPermission($user, DocxBuilderPermissions::PRESETS_CREATE);
    }

    public function update(mixed $user, DocumentPreset $preset): bool
    {
        return $this->hasPermission($user, DocxBuilderPermissions::PRESETS_UPDATE);
    }

    public function delete(mixed $user, DocumentPreset $preset): bool
    {
        return $this->hasPermission($user, DocxBuilderPermissions::PRESETS_DELETE);
    }
}
