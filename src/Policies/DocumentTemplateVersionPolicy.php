<?php

namespace Arseno25\DocxBuilder\Policies;

use Arseno25\DocxBuilder\Models\DocumentTemplateVersion;
use Arseno25\DocxBuilder\Policies\Concerns\ChecksDocxBuilderPermissions;
use Arseno25\DocxBuilder\Support\DocxBuilderPermissions;

class DocumentTemplateVersionPolicy
{
    use ChecksDocxBuilderPermissions;

    public function viewAny(mixed $user): bool
    {
        return $this->hasPermission($user, DocxBuilderPermissions::VERSIONS_VIEW_ANY);
    }

    public function view(mixed $user, DocumentTemplateVersion $version): bool
    {
        return $this->viewAny($user);
    }

    public function create(mixed $user): bool
    {
        return $this->hasPermission($user, DocxBuilderPermissions::VERSIONS_CREATE);
    }

    public function update(mixed $user, DocumentTemplateVersion $version): bool
    {
        return $this->hasPermission($user, DocxBuilderPermissions::VERSIONS_UPDATE);
    }

    public function delete(mixed $user, DocumentTemplateVersion $version): bool
    {
        return $this->hasPermission($user, DocxBuilderPermissions::VERSIONS_DELETE);
    }
}
