<?php

use Arseno25\DocxBuilder\Models\DocumentTemplate;
use Arseno25\DocxBuilder\Models\DocumentTemplateField;
use Arseno25\DocxBuilder\Models\DocumentTemplateVersion;
use Arseno25\DocxBuilder\Support\TemplateValidator;
use Arseno25\DocxBuilder\Tests\Support\DocxFixtureFactory;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Storage;

it('warns when template has no active version', function () {
    $template = DocumentTemplate::create([
        'code' => 'TMP-1',
        'name' => 'Template 1',
        'status' => 'draft',
        'visibility' => 'internal',
        'payload_snapshot_policy' => 'off',
    ]);

    $messages = app(TemplateValidator::class)->validate($template);

    expect($messages)->toContain('Template has no active version.');
});

it(
    'enforces unique keys and placeholder tags per template at database level',
    function () {
        $template = DocumentTemplate::create([
            'code' => 'TMP-2',
            'name' => 'Template 2',
            'status' => 'draft',
            'visibility' => 'internal',
            'payload_snapshot_policy' => 'off',
        ]);

        DocumentTemplateField::create([
            'template_id' => $template->id,
            'label' => 'A',
            'key' => 'name',
            'type' => 'text',
            'placeholder_tag' => '[doc.name]',
            'required' => false,
        ]);

        expect(
            fn() => DocumentTemplateField::create([
                'template_id' => $template->id,
                'label' => 'B',
                'key' => 'name',
                'type' => 'text',
                'placeholder_tag' => '[doc.name]',
                'required' => false,
            ]),
        )->toThrow(UniqueConstraintViolationException::class);
    },
);

it(
    'warns when schema fields are not in sync with template placeholders (PRD 15.5)',
    function () {
        Storage::fake('local');
        config()->set('docx-builder.template_disk', 'local');

        $docx = DocxFixtureFactory::minimalTextDocx(
            <<<XML
            <w:p><w:r><w:t>[doc.name]</w:t></w:r></w:p>
            <w:p><w:r><w:t>[doc.address]</w:t></w:r></w:p>
            XML
            ,
        );

        Storage::disk('local')->put('templates/source.docx', $docx);

        $template = DocumentTemplate::create([
            'code' => 'TMP-TV-SYNC',
            'name' => 'Template',
            'status' => 'draft',
            'visibility' => 'internal',
            'payload_snapshot_policy' => 'off',
        ]);

        $version = DocumentTemplateVersion::create([
            'template_id' => $template->id,
            'version' => 'v1',
            'is_active' => true,
            'source_disk' => 'local',
            'source_path' => 'templates/source.docx',
        ]);

        $template->active_version_id = $version->id;
        $template->save();

        DocumentTemplateField::create([
            'template_id' => $template->id,
            'label' => 'Name',
            'key' => 'name',
            'type' => 'text',
            'placeholder_tag' => '[doc.name]',
            'required' => false,
        ]);

        DocumentTemplateField::create([
            'template_id' => $template->id,
            'label' => 'Phone',
            'key' => 'phone',
            'type' => 'text',
            'placeholder_tag' => '[doc.phone]',
            'required' => false,
        ]);

        $messages = app(TemplateValidator::class)->validate($template->fresh());

        expect($messages)->toContain(
            'Template placeholder has no mapped field: [doc.address]',
        );
        expect($messages)->toContain(
            'Field placeholder not found in template: [doc.phone]',
        );
    },
);
