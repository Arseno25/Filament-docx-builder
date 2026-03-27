<?php

namespace Arseno25\DocxBuilder\Policies;

use Arseno25\DocxBuilder\Models\DocumentTemplateCategory;
use Arseno25\DocxBuilder\Policies\Concerns\ChecksDocxBuilderPermissions;
use Arseno25\DocxBuilder\Support\DocxBuilderPermissions;

class DocumentTemplateCategoryPolicy
{
    use ChecksDocxBuilderPermissions;

    public function viewAny(mixed $user): bool
    {
        return $this->hasPermission($user, DocxBuilderPermissions::CATEGORIES_VIEW_ANY);
    }

    public function view(mixed $user, DocumentTemplateCategory $category): bool
    {
        return $this->viewAny($user);
    }

    public function create(mixed $user): bool
    {
        return $this->hasPermission($user, DocxBuilderPermissions::CATEGORIES_CREATE);
    }

    public function update(mixed $user, DocumentTemplateCategory $category): bool
    {
        return $this->hasPermission($user, DocxBuilderPermissions::CATEGORIES_UPDATE);
    }

    public function delete(mixed $user, DocumentTemplateCategory $category): bool
    {
        return $this->hasPermission($user, DocxBuilderPermissions::CATEGORIES_DELETE);
    }
}
