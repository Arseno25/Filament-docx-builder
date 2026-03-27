<?php

namespace Arseno25\DocxBuilder\Services;

use Arseno25\DocxBuilder\Contracts\DocxToPdfConverterInterface;
use Arseno25\DocxBuilder\Models\DocumentTemplateVersion;
use Arseno25\DocxBuilder\Support\DocxTemplateEngine;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use RuntimeException;

class DocxLayoutPreviewService
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function previewPdfUrl(
        DocumentTemplateVersion $version,
        array $payload,
    ): string {
        if (!config('docx-builder.preview.layout.enabled', false)) {
            throw new RuntimeException('Layout preview is disabled.');
        }

        $ttlMinutes = (int) config('docx-builder.preview.layout.ttl_minutes', 10);
        $ttlMinutes = $ttlMinutes > 0 ? $ttlMinutes : 10;

        $payloadHash = sha1((string) json_encode($payload));
        $key = sha1((string) $version->getKey() . ':' . $payloadHash);

        $cacheKey = "docx-builder:layout-preview:{$key}";

        $meta = Cache::get($cacheKey);
        if (is_array($meta) && isset($meta['disk'], $meta['path'])) {
            $disk = (string) $meta['disk'];
            $path = (string) $meta['path'];

            if (Storage::disk($disk)->exists($path)) {
                return $this->signedUrl($key, $ttlMinutes);
            }
        }

        $pdfBytes = $this->renderPdfBytes($version, $payload);

        $disk = (string) (config('docx-builder.preview.layout.disk') ?:
            config('docx-builder.output_disk', 'local'));

        $prefix = trim(
            (string) config(
                'docx-builder.preview.layout.path_prefix',
                'docx-builder/previews',
            ),
            '/',
        );

        $filename = "preview_{$version->getKey()}_{$payloadHash}.pdf";
        $path = "{$prefix}/{$filename}";

        Storage::disk($disk)->put($path, $pdfBytes);

        Cache::put(
            $cacheKey,
            ['disk' => $disk, 'path' => $path],
            now()->addMinutes($ttlMinutes),
        );

        return $this->signedUrl($key, $ttlMinutes);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function renderPdfBytes(
        DocumentTemplateVersion $version,
        array $payload,
    ): string {
        $disk = (string) $version->source_disk;
        $path = (string) $version->source_path;

        if (!Storage::disk($disk)->exists($path)) {
            throw new RuntimeException(
                "Template source not found on disk [{$disk}] at path [{$path}].",
            );
        }

        $templateBytes = (string) Storage::disk($disk)->get($path);

        $renderedDocx = app(DocxTemplateEngine::class)->render(
            $templateBytes,
            $payload,
        );

        /** @var DocxToPdfConverterInterface $converter */
        $converter = app(DocxToPdfConverterInterface::class);

        return $converter->convertDocxBytesToPdfBytes($renderedDocx);
    }

    private function signedUrl(string $key, int $ttlMinutes): string
    {
        return URL::temporarySignedRoute(
            'docx-builder.preview.pdf',
            now()->addMinutes($ttlMinutes),
            ['key' => $key],
        );
    }
}
