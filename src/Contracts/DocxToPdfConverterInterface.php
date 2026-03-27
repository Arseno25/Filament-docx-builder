<?php

namespace Arseno25\DocxBuilder\Contracts;

interface DocxToPdfConverterInterface
{
    public function convertDocxBytesToPdfBytes(string $docxBytes): string;
}
