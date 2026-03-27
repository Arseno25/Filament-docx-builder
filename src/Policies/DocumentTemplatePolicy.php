<?php

namespace Arseno25\DocxBuilder\Policies;

use Arseno25\DocxBuilder\Models\DocumentTemplate;
use Arseno25\DocxBuilder\Policies\Concerns\ChecksDocxBuilderPermissions;
use Arseno25\DocxBuilder\Support\DocxBuilderPermissions;

class DocumentTemplatePolicy
{
    use ChecksDocxBuilderPermissions;

    public function viewAny(mixed $user): bool
    {
        return $this->hasPermission($user, DocxBuilderPermissions::TEMPLATES_VIEW_ANY);
    }

    public function view(mixed $user, DocumentTemplate $template): bool
    {
        return $this->viewAny($user);
    }

    public function create(mixed $user): bool
    {
        return $this->hasPermission($user, DocxBuilderPermissions::TEMPLATES_CREATE);
    }

    public function update(mixed $user, DocumentTemplate $template): bool
    {
        return $this->hasPermission($user, DocxBuilderPermissions::TEMPLATES_UPDATE);
    }

    public function delete(mixed $user, DocumentTemplate $template): bool
    {
        return $this->hasPermission($user, DocxBuilderPermissions::TEMPLATES_DELETE);
    }
}
