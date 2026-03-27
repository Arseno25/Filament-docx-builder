<?php

namespace Arseno25\DocxBuilder\Support;

use Arseno25\DocxBuilder\Models\DocumentTemplate;
use Arseno25\DocxBuilder\Models\DocumentTemplateVersion;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

class TemplateValidator
{
    /**
     * @return array<int, string> list of warnings/errors
     */
    public function validate(DocumentTemplate $template): array
    {
        $messages = [];

        if ($template->is_archived) {
            $messages[] = 'Template is archived.';
        }

        if (!$template->active_version_id) {
            $messages[] = 'Template has no active version.';
        }

        $fields = $template
            ->fields()
            ->get(['key', 'placeholder_tag', 'required']);

        $duplicateKeys = $this->findDuplicates($fields->pluck('key')->filter());
        foreach ($duplicateKeys as $key) {
            $messages[] = "Duplicate field key: {$key}";
        }

        $duplicateTags = $this->findDuplicates(
            $fields->pluck('placeholder_tag')->filter(),
        );
        foreach ($duplicateTags as $tag) {
            $messages[] = "Duplicate placeholder tag: {$tag}";
        }

        $activeVersion = $template->activeVersion()->first();
        if ($activeVersion) {
            try {
                $placeholderTags = $this->extractPlaceholderTags(
                    $activeVersion,
                );

                $schemaTags = $fields
                    ->pluck('placeholder_tag')
                    ->filter(fn($tag) => filled($tag))
                    ->map(fn($tag) => (string) $tag)
                    ->values()
                    ->all();

                foreach ($schemaTags as $tag) {
                    if (!in_array($tag, $placeholderTags, true)) {
                        $messages[] = "Field placeholder not found in template: {$tag}";
                    }
                }

                foreach ($placeholderTags as $tag) {
                    if (!in_array($tag, $schemaTags, true)) {
                        $messages[] = "Template placeholder has no mapped field: {$tag}";
                    }
                }
            } catch (\Throwable $e) {
                $messages[] =
                    'Unable to inspect template placeholders: ' .
                    $e->getMessage();
            }
        }

        return $messages;
    }

    /**
     * @param  Collection<int, string>  $values
     * @return array<int, string>
     */
    private function findDuplicates(Collection $values): array
    {
        return $values
            ->countBy()
            ->filter(fn(int $count) => $count > 1)
            ->keys()
            ->values()
            ->all();
    }

    /**
     * @return array<int, string> placeholder tags, e.g. [doc.name]
     */
    public function extractPlaceholderTags(
        DocumentTemplateVersion $version,
    ): array {
        $disk = (string) $version->source_disk;
        $path = (string) $version->source_path;

        if (!Storage::disk($disk)->exists($path)) {
            throw new RuntimeException(
                "Template source not found on disk [{$disk}] at path [{$path}].",
            );
        }

        $bytes = (string) Storage::disk($disk)->get($path);

        $tmp = tempnam(sys_get_temp_dir(), 'docx_inspect_');
        if ($tmp === false) {
            throw new RuntimeException(
                'Unable to create a temporary file to inspect the DOCX template.',
            );
        }

        file_put_contents($tmp, $bytes);

        $zip = new ZipArchive();
        if ($zip->open($tmp) !== true) {
            @unlink($tmp);
            throw new RuntimeException(
                'Unable to open DOCX template as a ZIP archive.',
            );
        }

        $tags = [];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string) $zip->getNameIndex($i);
            if (!Str::startsWith($name, 'word/')) {
                continue;
            }
            if (!Str::endsWith($name, '.xml')) {
                continue;
            }

            $xml = $zip->getFromName($name);
            if ($xml === false) {
                continue;
            }

            $xml = (string) $xml;

            preg_match_all('/\\[(doc\\.[a-zA-Z0-9_\\.]+)\\]/', $xml, $m1);
            preg_match_all(
                '/\\{\\{\\#?(doc\\.[a-zA-Z0-9_\\.]+)\\}\\}/',
                $xml,
                $m2,
            );
            preg_match_all(
                '/\\{\\{\\#(if|unless|each)\\s+(doc\\.[a-zA-Z0-9_\\.]+)\\s*\\}\\}/',
                $xml,
                $m3,
            );

            foreach ($m1[1] ?? [] as $path) {
                $tags[] = '[' . $path . ']';
            }

            foreach ($m2[1] ?? [] as $path) {
                $tags[] = '{{' . $path . '}}';
            }

            foreach ($m3[2] ?? [] as $path) {
                $tags[] = '{{' . $path . '}}';
            }
        }

        $zip->close();
        @unlink($tmp);

        $tags = array_values(array_unique($tags));
        sort($tags);

        return $tags;
    }
}
