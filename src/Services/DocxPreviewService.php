<?php

namespace Arseno25\DocxBuilder\Services;

use Arseno25\DocxBuilder\Models\DocumentTemplateVersion;
use Arseno25\DocxBuilder\Support\DocxTemplateEngine;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;

class DocxPreviewService
{
    /**
     * Render a lightweight preview of the DOCX by extracting text from `word/document.xml`.
     *
     * @param  array<string, mixed>  $payload
     */
    public function previewText(
        DocumentTemplateVersion $version,
        array $payload,
        int $maxChars = 12000,
    ): string {
        $disk = (string) $version->source_disk;
        $path = (string) $version->source_path;

        if (! Storage::disk($disk)->exists($path)) {
            throw new RuntimeException(
                "Template source not found on disk [{$disk}] at path [{$path}].",
            );
        }

        $templateBytes = (string) Storage::disk($disk)->get($path);

        $rendered = app(DocxTemplateEngine::class)->render($templateBytes, $payload);

        $tmp = tempnam(sys_get_temp_dir(), 'docx_preview_');
        if ($tmp === false) {
            throw new RuntimeException('Unable to create a temporary preview file.');
        }

        file_put_contents($tmp, $rendered);

        $zip = new ZipArchive();
        if ($zip->open($tmp) !== true) {
            @unlink($tmp);
            throw new RuntimeException('Unable to open rendered DOCX for preview.');
        }

        $xml = $zip->getFromName('word/document.xml');
        $zip->close();
        @unlink($tmp);

        if ($xml === false) {
            return '';
        }

        $text = $this->extractTextFromWordXml((string) $xml);

        if (mb_strlen($text) > $maxChars) {
            $text = mb_substr($text, 0, $maxChars) . "\n\n…";
        }

        return $text;
    }

    private function extractTextFromWordXml(string $xml): string
    {
        $xml = preg_replace('/<w:tab\\b[^\\/>]*\\/>/i', "\t", $xml) ?? $xml;
        $xml = preg_replace('/<w:br\\b[^\\/>]*\\/>/i', "\n", $xml) ?? $xml;
        $xml = preg_replace('/<\\/w:p\\s*>/i', "\n", $xml) ?? $xml;

        $xml = preg_replace('/<[^>]+>/', '', $xml) ?? $xml;

        $xml = html_entity_decode($xml, ENT_QUOTES | ENT_XML1, 'UTF-8');

        $xml = preg_replace("/\\r\\n|\\r/", "\n", $xml) ?? $xml;
        $xml = preg_replace("/\\n{3,}/", "\n\n", $xml) ?? $xml;

        return trim($xml);
    }
}
