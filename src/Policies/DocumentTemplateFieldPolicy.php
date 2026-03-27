<?php

namespace Arseno25\DocxBuilder\Policies;

use Arseno25\DocxBuilder\Models\DocumentTemplateField;
use Arseno25\DocxBuilder\Policies\Concerns\ChecksDocxBuilderPermissions;
use Arseno25\DocxBuilder\Support\DocxBuilderPermissions;

class DocumentTemplateFieldPolicy
{
    use ChecksDocxBuilderPermissions;

    public function viewAny(mixed $user): bool
    {
        return $this->hasPermission($user, DocxBuilderPermissions::FIELDS_VIEW_ANY);
    }

    public function view(mixed $user, DocumentTemplateField $field): bool
    {
        return $this->viewAny($user);
    }

    public function create(mixed $user): bool
    {
        return $this->hasPermission($user, DocxBuilderPermissions::FIELDS_CREATE);
    }

    public function update(mixed $user, DocumentTemplateField $field): bool
    {
        return $this->hasPermission($user, DocxBuilderPermissions::FIELDS_UPDATE);
    }

    public function delete(mixed $user, DocumentTemplateField $field): bool
    {
        return $this->hasPermission($user, DocxBuilderPermissions::FIELDS_DELETE);
    }
}
