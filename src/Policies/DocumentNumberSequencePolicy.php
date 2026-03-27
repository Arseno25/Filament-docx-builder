<?php

namespace Arseno25\DocxBuilder\Policies;

use Arseno25\DocxBuilder\Models\DocumentNumberSequence;
use Arseno25\DocxBuilder\Policies\Concerns\ChecksDocxBuilderPermissions;
use Arseno25\DocxBuilder\Support\DocxBuilderPermissions;

class DocumentNumberSequencePolicy
{
    use ChecksDocxBuilderPermissions;

    public function viewAny(mixed $user): bool
    {
        return $this->hasPermission($user, DocxBuilderPermissions::SEQUENCES_VIEW_ANY);
    }

    public function view(mixed $user, DocumentNumberSequence $sequence): bool
    {
        return $this->viewAny($user);
    }

    public function create(mixed $user): bool
    {
        return $this->hasPermission($user, DocxBuilderPermissions::SEQUENCES_CREATE);
    }

    public function update(mixed $user, DocumentNumberSequence $sequence): bool
    {
        return $this->hasPermission($user, DocxBuilderPermissions::SEQUENCES_UPDATE);
    }

    public function delete(mixed $user, DocumentNumberSequence $sequence): bool
    {
        return $this->hasPermission($user, DocxBuilderPermissions::SEQUENCES_DELETE);
    }
}
