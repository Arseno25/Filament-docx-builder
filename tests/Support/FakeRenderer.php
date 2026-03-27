<?php

namespace Arseno25\DocxBuilder\Tests\Support;

use Arseno25\DocxBuilder\Models\DocumentTemplateVersion;
use Arseno25\DocxBuilder\Rendering\RendererInterface;
use RuntimeException;

class FakeRenderer implements RendererInterface
{
    public function __construct(
        private readonly string $bytes = 'FAKE-DOCX-BYTES',
        private readonly bool $shouldThrow = false,
    ) {}

    public function render(DocumentTemplateVersion $version, array $payload): string
    {
        if ($this->shouldThrow) {
            throw new RuntimeException('Renderer failed.');
        }

        return $this->bytes;
    }
}
