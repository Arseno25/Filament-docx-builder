<?php

namespace Arseno25\DocxBuilder\Services;

use Arseno25\DocxBuilder\Models\DocumentTemplate;
use Arseno25\DocxBuilder\Models\DocumentTemplateVersion;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class TemplateVersionService
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createVersion(
        DocumentTemplate $template,
        array $attributes,
    ): DocumentTemplateVersion {
        $attributes['template_id'] = $template->getKey();
        $attributes['schema_snapshot'] =
            $attributes['schema_snapshot'] ??
            $this->makeSchemaSnapshot($template);

        /** @var DocumentTemplateVersion $version */
        $version = $template->versions()->create($attributes);

        if (($attributes['is_active'] ?? false) === true) {
            $this->setActive($template, $version);
        }

        return $version->fresh();
    }

    public function setActive(
        DocumentTemplate $template,
        DocumentTemplateVersion $version,
    ): void {
        if ($version->template_id !== $template->id) {
            throw new RuntimeException('Version does not belong to template.');
        }

        DB::transaction(function () use ($template, $version) {
            $template->versions()->update(['is_active' => false]);
            $version->forceFill(['is_active' => true])->save();

            $template->forceFill(['active_version_id' => $version->id])->save();
        });
    }

    public function rollbackToPrevious(
        DocumentTemplate $template,
    ): DocumentTemplateVersion {
        $current = $template->activeVersion()->first();
        if (!$current) {
            throw new RuntimeException('Template has no active version.');
        }

        $previous = $template
            ->versions()
            ->where('id', '<', $current->getKey())
            ->orderByDesc('id')
            ->first();

        if (!$previous) {
            throw new RuntimeException(
                'No previous version exists to rollback to.',
            );
        }

        $this->setActive($template, $previous);

        return $previous->fresh();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function makeSchemaSnapshot(DocumentTemplate $template): array
    {
        return $template
            ->fields()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get([
                'group_name',
                'sort_order',
                'label',
                'key',
                'type',
                'placeholder_tag',
                'required',
                'default_value',
                'help_text',
                'validation_rules',
                'visibility_rules',
                'transform_rules',
                'data_source_type',
                'data_source_config',
            ])
            ->toArray();
    }
}
