<?php

use Arseno25\DocxBuilder\Models\DocumentTemplate;
use Arseno25\DocxBuilder\Models\DocumentTemplateVersion;
use Arseno25\DocxBuilder\Rendering\OpenTbsRenderer;
use Arseno25\DocxBuilder\Tests\Support\DocxFixtureFactory;
use Illuminate\Support\Facades\Storage;

it(
    'renders scalar placeholders, conditionals, and repeats into the DOCX XML parts',
    function () {
        Storage::fake('local');

        $docx = DocxFixtureFactory::minimalTextDocx(
            <<<XML
            <w:p><w:r><w:t>[doc.name]</w:t></w:r></w:p>
            <w:p><w:r><w:t>{{#if doc.show}}VISIBLE{{/if}}</w:t></w:r></w:p>
            <w:p><w:r><w:t>{{#unless doc.hide}}NOT_HIDDEN{{/unless}}</w:t></w:r></w:p>
            {{#each doc.items}}<w:p><w:r><w:t>{{name}}</w:t></w:r></w:p>{{/each}}
            XML
            ,
        );

        Storage::disk('local')->put('templates/t.docx', $docx);

        $template = DocumentTemplate::create([
            'code' => 'TMP-R',
            'name' => 'Template R',
            'status' => 'draft',
            'visibility' => 'internal',
            'payload_snapshot_policy' => 'off',
        ]);

        $version = DocumentTemplateVersion::create([
            'template_id' => $template->id,
            'version' => 'v1',
            'is_active' => true,
            'source_disk' => 'local',
            'source_path' => 'templates/t.docx',
        ]);

        $bytes = app(OpenTbsRenderer::class)->render($version, [
            'doc' => [
                'name' => 'Alice',
                'show' => true,
                'hide' => '',
                'items' => [['name' => 'One'], ['name' => 'Two']],
            ],
        ]);

        $tmp = tempnam(sys_get_temp_dir(), 'docx_out_');
        file_put_contents($tmp, $bytes);

        $zip = new ZipArchive();
        expect($zip->open($tmp))->toBeTrue();

        $documentXml = (string) $zip->getFromName('word/document.xml');
        $zip->close();
        @unlink($tmp);

        expect($documentXml)->toContain('Alice');
        expect($documentXml)->toContain('VISIBLE');
        expect($documentXml)->toContain('NOT_HIDDEN');
        expect($documentXml)->toContain('One');
        expect($documentXml)->toContain('Two');
    },
);

it(
    'replaces an image target when a picture docPr marker matches an image placeholder',
    function () {
        Storage::fake('local');

        $documentXmlBody = <<<XML
        <w:p>
          <w:r>
            <w:drawing>
              <wp:inline>
                <wp:docPr id="1" name="Logo" descr="[doc.logo]"/>
                <a:graphic>
                  <a:graphicData>
                    <a:blip r:embed="rId9"/>
                  </a:graphicData>
                </a:graphic>
              </wp:inline>
            </w:drawing>
          </w:r>
        </w:p>
        XML;

        $docx = DocxFixtureFactory::minimalImageDocx(
            $documentXmlBody,
            'rId9',
            'media/image1.png',
            'OLD',
        );

        Storage::disk('local')->put('templates/img.docx', $docx);

        $template = DocumentTemplate::create([
            'code' => 'TMP-R-IMG',
            'name' => 'Template Image',
            'status' => 'draft',
            'visibility' => 'internal',
            'payload_snapshot_policy' => 'off',
        ]);

        $version = DocumentTemplateVersion::create([
            'template_id' => $template->id,
            'version' => 'v1',
            'is_active' => true,
            'source_disk' => 'local',
            'source_path' => 'templates/img.docx',
        ]);

        $bytes = app(OpenTbsRenderer::class)->render($version, [
            'doc' => [
                'logo' => ['bytes' => 'NEW'],
            ],
        ]);

        $tmp = tempnam(sys_get_temp_dir(), 'docx_out_');
        file_put_contents($tmp, $bytes);

        $zip = new ZipArchive();
        expect($zip->open($tmp))->toBeTrue();

        $img = (string) $zip->getFromName('word/media/image1.png');
        $zip->close();
        @unlink($tmp);

        expect($img)->toBe('NEW');
    },
);

it('throws when template source is missing', function () {
    Storage::fake('local');

    $template = DocumentTemplate::create([
        'code' => 'TMP-R2',
        'name' => 'Template R2',
        'status' => 'draft',
        'visibility' => 'internal',
        'payload_snapshot_policy' => 'off',
    ]);

    $version = DocumentTemplateVersion::create([
        'template_id' => $template->id,
        'version' => 'v1',
        'is_active' => true,
        'source_disk' => 'local',
        'source_path' => 'templates/missing.docx',
    ]);

    app(OpenTbsRenderer::class)->render($version, []);
})->throws(RuntimeException::class);
