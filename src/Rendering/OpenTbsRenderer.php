<?php

namespace Arseno25\DocxBuilder\Rendering;

use Arseno25\DocxBuilder\Models\DocumentTemplateVersion;
use Arseno25\DocxBuilder\Support\DocxTemplateEngine;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class OpenTbsRenderer implements RendererInterface
{
    public function render(
        DocumentTemplateVersion $version,
        array $payload,
    ): string {
        $disk = $version->source_disk;
        $path = $version->source_path;

        if (!Storage::disk($disk)->exists($path)) {
            throw new RuntimeException(
                "Template source not found on disk [{$disk}] at path [{$path}].",
            );
        }

        $bytes = (string) Storage::disk($disk)->get($path);

        $doc = Arr::get($payload, 'doc', []);
        $images = [];

        if (is_array($doc)) {
            foreach ($doc as $key => $value) {
                if (!is_string($key) || $key === '') {
                    continue;
                }

                if (!is_array($value)) {
                    continue;
                }

                if (
                    !array_key_exists('bytes', $value) &&
                    !array_key_exists('path', $value)
                ) {
                    continue;
                }

                $images[$key] = $value;
            }
        }

        return app(DocxTemplateEngine::class)->render(
            $bytes,
            $payload,
            $images,
        );
    }
}
