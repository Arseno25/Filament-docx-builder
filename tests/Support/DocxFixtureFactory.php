<?php

namespace Arseno25\DocxBuilder\Tests\Support;

use RuntimeException;
use ZipArchive;

class DocxFixtureFactory
{
    public static function make(array $entries): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'docx_tpl_');
        if ($tmp === false) {
            throw new RuntimeException('Unable to create temp file for DOCX fixture.');
        }

        $zip = new ZipArchive();
        if ($zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            @unlink($tmp);
            throw new RuntimeException('Unable to create DOCX fixture zip.');
        }

        foreach ($entries as $path => $contents) {
            $zip->addFromString((string) $path, (string) $contents);
        }

        $zip->close();

        $bytes = file_get_contents($tmp);
        @unlink($tmp);

        return (string) $bytes;
    }

    public static function minimalTextDocx(string $documentXmlBody): string
    {
        $documentXml = self::wrapDocumentXml($documentXmlBody);

        return self::make([
            '[Content_Types].xml' => self::contentTypesXml(),
            '_rels/.rels' => self::rootRelsXml(),
            'word/document.xml' => $documentXml,
        ]);
    }

    public static function minimalImageDocx(string $documentXmlBody, string $rid, string $mediaTarget, string $initialImageBytes): string
    {
        $documentXml = self::wrapDocumentXml($documentXmlBody);

        $rels = <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="{$rid}" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="{$mediaTarget}"/>
</Relationships>
XML;

        return self::make([
            '[Content_Types].xml' => self::contentTypesXml(withImage: true),
            '_rels/.rels' => self::rootRelsXml(),
            'word/document.xml' => $documentXml,
            'word/_rels/document.xml.rels' => $rels,
            "word/{$mediaTarget}" => $initialImageBytes,
        ]);
    }

    private static function wrapDocumentXml(string $bodyInnerXml): string
    {
        return <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"
    xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"
    xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing"
    xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main">
  <w:body>
    {$bodyInnerXml}
  </w:body>
</w:document>
XML;
    }

    private static function contentTypesXml(bool $withImage = false): string
    {
        $imageOverride = $withImage
            ? '<Default Extension="png" ContentType="image/png"/>'
            : '';

        return <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  {$imageOverride}
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
</Types>
XML;
    }

    private static function rootRelsXml(): string
    {
        return <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>
XML;
    }
}
