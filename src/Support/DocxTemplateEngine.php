<?php

namespace Arseno25\DocxBuilder\Support;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

class DocxTemplateEngine
{
    /**
     * Render a DOCX template (ZIP) by applying a small, safe template syntax on XML parts.
     *
     * Supported:
     * - Scalar placeholders: `[doc.key]` and `{{doc.key}}`
     * - Conditionals: `{{#if doc.key}} ... {{/if}}`, `{{#unless doc.key}} ... {{/unless}}`
     * - Repeats: `{{#each doc.items}} ... {{/each}}` with inner `{{key}}` or `{{this.key}}`
     *
     * Images:
     * - If an image placeholder is found via a `wp:docPr` attribute (`descr` or `title`) equal to
     *   `[doc.key]` or `{{doc.key}}`, the corresponding target in `document.xml.rels` will be replaced.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, array{disk?: string, path?: string, bytes?: string, filename?: string}>  $images
     */
    public function render(
        string $templateBytes,
        array $payload,
        array $images = [],
    ): string {
        $tmp = tempnam(sys_get_temp_dir(), 'docx_');
        if ($tmp === false) {
            throw new RuntimeException(
                'Unable to create a temporary file for DOCX rendering.',
            );
        }

        file_put_contents($tmp, $templateBytes);

        $zip = new ZipArchive();
        if ($zip->open($tmp) !== true) {
            @unlink($tmp);
            throw new RuntimeException(
                'Unable to open DOCX template as a ZIP archive.',
            );
        }

        $this->renderXmlParts($zip, $payload);
        $this->replaceImages($zip, $payload, $images);

        $zip->close();

        $out = file_get_contents($tmp);
        @unlink($tmp);

        return (string) $out;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function renderXmlParts(ZipArchive $zip, array $payload): void
    {
        $xmlPaths = [];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string) $zip->getNameIndex($i);
            if (!Str::startsWith($name, 'word/')) {
                continue;
            }
            if (!Str::endsWith($name, '.xml')) {
                continue;
            }

            $xmlPaths[] = $name;
        }

        foreach ($xmlPaths as $xmlPath) {
            $xml = $zip->getFromName($xmlPath);
            if ($xml === false) {
                continue;
            }

            $xml = $this->applyBlocks((string) $xml, $payload);
            $xml = $this->applyScalars((string) $xml, $payload);

            $zip->addFromString($xmlPath, $xml);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function applyBlocks(string $xml, array $payload): string
    {
        $xml = $this->applyEachBlocks($xml, $payload);
        $xml = $this->applyIfBlocks($xml, $payload);
        $xml = $this->applyUnlessBlocks($xml, $payload);

        return $xml;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function applyEachBlocks(string $xml, array $payload): string
    {
        $pattern =
            '/\{\{\#each\s+([a-zA-Z0-9_\.]+)\s*\}\}(.*?)\{\{\/each\}\}/s';

        return (string) preg_replace_callback(
            $pattern,
            function (array $m) use ($payload): string {
                $path = (string) $m[1];
                $inner = (string) $m[2];

                $value = $this->resolve($payload, $path);
                if (!is_array($value)) {
                    return '';
                }

                $out = '';

                foreach (array_values($value) as $item) {
                    $context = is_array($item) ? $item : ['this' => $item];
                    $rendered = $this->applyBlocks($inner, [
                        'doc' => Arr::get($payload, 'doc', []),
                        ...$context,
                    ]);
                    $rendered = $this->applyScalars(
                        $rendered,
                        ['doc' => Arr::get($payload, 'doc', []), ...$context],
                        $context,
                    );
                    $out .= $rendered;
                }

                return $out;
            },
            $xml,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function applyIfBlocks(string $xml, array $payload): string
    {
        $pattern = '/\{\{\#if\s+([a-zA-Z0-9_\.]+)\s*\}\}(.*?)\{\{\/if\}\}/s';

        return (string) preg_replace_callback(
            $pattern,
            function (array $m) use ($payload): string {
                $path = (string) $m[1];
                $inner = (string) $m[2];

                $value = $this->resolve($payload, $path);
                if ($this->isTruthy($value)) {
                    return $inner;
                }

                return '';
            },
            $xml,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function applyUnlessBlocks(string $xml, array $payload): string
    {
        $pattern =
            '/\{\{\#unless\s+([a-zA-Z0-9_\.]+)\s*\}\}(.*?)\{\{\/unless\}\}/s';

        return (string) preg_replace_callback(
            $pattern,
            function (array $m) use ($payload): string {
                $path = (string) $m[1];
                $inner = (string) $m[2];

                $value = $this->resolve($payload, $path);
                if (!$this->isTruthy($value)) {
                    return $inner;
                }

                return '';
            },
            $xml,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $itemContext
     */
    private function applyScalars(
        string $xml,
        array $payload,
        array $itemContext = [],
    ): string {
        $xml = $this->replaceScalarPlaceholders(
            $xml,
            $payload,
            $itemContext,
            '/\[(?<path>[a-zA-Z0-9_\.]+)\]/',
        );
        $xml = $this->replaceScalarPlaceholders(
            $xml,
            $payload,
            $itemContext,
            '/\{\{(?<path>[a-zA-Z0-9_\.]+)\}\}/',
        );

        return $xml;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $itemContext
     */
    private function replaceScalarPlaceholders(
        string $xml,
        array $payload,
        array $itemContext,
        string $pattern,
    ): string {
        return (string) preg_replace_callback(
            $pattern,
            function (array $m) use ($payload, $itemContext): string {
                $path = (string) ($m['path'] ?? '');
                if ($path === '') {
                    return '';
                }

                if (
                    $path === 'this' &&
                    array_key_exists('this', $itemContext)
                ) {
                    return $this->escapeForXml($itemContext['this']);
                }

                if (
                    Str::startsWith($path, 'this.') &&
                    array_key_exists('this', $itemContext)
                ) {
                    $sub = Str::after($path, 'this.');
                    $value = is_array($itemContext['this'])
                        ? Arr::get($itemContext['this'], $sub)
                        : null;

                    return $this->escapeForXml($value);
                }

                if (array_key_exists($path, $itemContext)) {
                    return $this->escapeForXml($itemContext[$path]);
                }

                $value = $this->resolve($payload, $path);
                if ($this->isImageValue($value)) {
                    return (string) ($m[0] ?? '');
                }

                return $this->escapeForXml($value);
            },
            $xml,
        );
    }

    private function escapeForXml(mixed $value): string
    {
        if (is_bool($value)) {
            $value = $value ? '1' : '0';
        }

        if ($value === null) {
            $value = '';
        }

        if (is_array($value) || is_object($value)) {
            $value =
                json_encode(
                    $value,
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
                ) ?:
                '';
        }

        return htmlspecialchars(
            (string) $value,
            ENT_XML1 | ENT_QUOTES,
            'UTF-8',
        );
    }

    private function isTruthy(mixed $value): bool
    {
        if ($value === null) {
            return false;
        }

        if ($value === false) {
            return false;
        }

        if (is_string($value)) {
            return trim($value) !== '';
        }

        if (is_array($value)) {
            return count($value) > 0;
        }

        return true;
    }

    private function isImageValue(mixed $value): bool
    {
        if (!is_array($value)) {
            return false;
        }

        if (array_key_exists('bytes', $value) && is_string($value['bytes'])) {
            return true;
        }

        if (array_key_exists('path', $value) && is_string($value['path'])) {
            return true;
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function resolve(array $payload, string $path): mixed
    {
        return Arr::get($payload, $path);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, array{disk?: string, path?: string, bytes?: string, filename?: string}>  $images
     */
    private function replaceImages(
        ZipArchive $zip,
        array $payload,
        array $images,
    ): void {
        if (empty($images)) {
            return;
        }

        $documentXml = $zip->getFromName('word/document.xml');
        $relsXml = $zip->getFromName('word/_rels/document.xml.rels');

        if ($documentXml === false || $relsXml === false) {
            return;
        }

        $documentXml = (string) $documentXml;
        $relsXml = (string) $relsXml;

        foreach ($images as $key => $image) {
            $tagA = "[doc.{$key}]";
            $tagB = "{{doc.{$key}}}";

            $rid = $this->findImageRelationshipIdForPlaceholder($documentXml, [
                $tagA,
                $tagB,
            ]);
            if (!$rid) {
                continue;
            }

            $target = $this->findRelationshipTarget($relsXml, $rid);
            if (!$target) {
                continue;
            }

            $mediaPath = Str::startsWith($target, '/')
                ? ltrim($target, '/')
                : $target;
            $mediaPath = Str::startsWith($mediaPath, 'word/')
                ? $mediaPath
                : "word/{$mediaPath}";

            $bytes = $image['bytes'] ?? null;
            if (
                $bytes === null &&
                filled($image['disk'] ?? null) &&
                filled($image['path'] ?? null)
            ) {
                $bytes = \Illuminate\Support\Facades\Storage::disk(
                    (string) $image['disk'],
                )->get((string) $image['path']);
            }

            if (!is_string($bytes) || $bytes === '') {
                continue;
            }

            $zip->deleteName($mediaPath);
            $zip->addFromString($mediaPath, $bytes);
        }
    }

    /**
     * Find the rId used by the image run that contains a docPr marker matching the placeholder.
     *
     * This is a pragmatic approach that works for templates where the picture has `descr` or `title`
     * set to the placeholder tag.
     *
     * @param  array<int, string>  $placeholders
     */
    private function findImageRelationshipIdForPlaceholder(
        string $documentXml,
        array $placeholders,
    ): ?string {
        $previous = libxml_use_internal_errors(true);

        $dom = new \DOMDocument();
        $loaded = $dom->loadXML($documentXml);

        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if ($loaded) {
            $xpath = new \DOMXPath($dom);
            $xpath->registerNamespace(
                'r',
                'http://schemas.openxmlformats.org/officeDocument/2006/relationships',
            );
            $xpath->registerNamespace(
                'wp',
                'http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing',
            );
            $xpath->registerNamespace(
                'a',
                'http://schemas.openxmlformats.org/drawingml/2006/main',
            );

            foreach ($placeholders as $placeholder) {
                $placeholder = (string) $placeholder;

                $query =
                    '//wp:docPr[@descr="' .
                    $placeholder .
                    '" or @title="' .
                    $placeholder .
                    '"]' .
                    '/ancestor::*[self::wp:inline or self::wp:anchor][1]' .
                    '//a:blip/@r:embed';

                /** @var \DOMNodeList $nodes */
                $nodes = $xpath->query($query);
                if ($nodes !== false && $nodes->length > 0) {
                    $rid = (string) $nodes->item(0)?->nodeValue;
                    if (filled($rid)) {
                        return $rid;
                    }
                }
            }
        }

        foreach ($placeholders as $placeholder) {
            $pos = strpos($documentXml, $placeholder);
            if ($pos === false) {
                continue;
            }

            $windowStart = max(0, $pos - 4000);
            $windowLen = 8000;
            $chunk = substr($documentXml, $windowStart, $windowLen);

            if (!preg_match('/r:embed=\"(?<rid>rId[0-9]+)\"/', $chunk, $m)) {
                continue;
            }

            return (string) ($m['rid'] ?? null);
        }

        return null;
    }

    private function findRelationshipTarget(
        string $relsXml,
        string $rid,
    ): ?string {
        $ridQuoted = preg_quote($rid, '/');
        if (
            !preg_match(
                '/Id=\"' . $ridQuoted . '\"[^>]+Target=\"(?<target>[^\"]+)\"/i',
                $relsXml,
                $m,
            )
        ) {
            return null;
        }

        return (string) ($m['target'] ?? null);
    }
}
