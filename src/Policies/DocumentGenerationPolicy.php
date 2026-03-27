<?php

namespace Arseno25\DocxBuilder\Policies;

use Arseno25\DocxBuilder\Models\DocumentGeneration;
use Arseno25\DocxBuilder\Policies\Concerns\ChecksDocxBuilderPermissions;
use Arseno25\DocxBuilder\Support\DocxBuilderPermissions;

class DocumentGenerationPolicy
{
    use ChecksDocxBuilderPermissions;

    public function viewAny(mixed $user): bool
    {
        return $this->hasPermission(
            $user,
            DocxBuilderPermissions::GENERATIONS_VIEW_ANY,
        );
    }

    public function view(mixed $user, DocumentGeneration $generation): bool
    {
        return $this->hasPermission(
            $user,
            DocxBuilderPermissions::GENERATIONS_VIEW,
        );
    }

    public function download(mixed $user, DocumentGeneration $generation): bool
    {
        return $this->hasPermission(
            $user,
            DocxBuilderPermissions::GENERATIONS_DOWNLOAD,
        );
    }

    public function retry(mixed $user, DocumentGeneration $generation): bool
    {
        return $this->hasPermission(
            $user,
            DocxBuilderPermissions::GENERATIONS_RETRY,
        );
    }
}
