<?php

namespace Arseno25\DocxBuilder\Rendering;

use Arseno25\DocxBuilder\Models\DocumentTemplateVersion;

interface RendererInterface
{
    /**
     * Render a DOCX using the given template version and payload.
     *
     * Implementations should throw a domain-specific exception on failure.
     *
     * @param  array<string, mixed>  $payload
     * @return string Binary DOCX contents
     */
    public function render(DocumentTemplateVersion $version, array $payload): string;
}
