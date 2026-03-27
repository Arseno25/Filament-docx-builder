<?php

namespace Arseno25\DocxBuilder\Tests\Support;

use Arseno25\DocxBuilder\Contracts\DocxToPdfConverterInterface;

class FakeDocxToPdfConverter implements DocxToPdfConverterInterface
{
    public function convertDocxBytesToPdfBytes(string $docxBytes): string
    {
        return "%PDF-1.4\n% Fake PDF preview\n";
    }
}
