<?php

use Arseno25\DocxBuilder\Models\DocumentNumberSequence;
use Arseno25\DocxBuilder\Models\DocumentPreset;
use Arseno25\DocxBuilder\Models\DocumentTemplate;
use Arseno25\DocxBuilder\Models\DocumentTemplateCategory;
use Arseno25\DocxBuilder\Models\DocumentTemplateField;
use Illuminate\Database\UniqueConstraintViolationException;

it('enforces unique sequence key per template', function () {
    $template = DocumentTemplate::create([
        'code' => 'TMP-C1',
        'name' => 'Template',
        'status' => 'draft',
        'visibility' => 'internal',
        'payload_snapshot_policy' => 'off',
    ]);

    DocumentNumberSequence::create([
        'template_id' => $template->id,
        'key' => 'document_number',
        'pattern' => '{seq:3}',
        'counter' => 0,
        'reset_policy' => 'never',
        'is_active' => true,
    ]);

    expect(
        fn() => DocumentNumberSequence::create([
            'template_id' => $template->id,
            'key' => 'document_number',
            'pattern' => '{seq:3}',
            'counter' => 0,
            'reset_policy' => 'never',
            'is_active' => true,
        ]),
    )->toThrow(UniqueConstraintViolationException::class);
});

it('enforces unique preset key per template', function () {
    $template = DocumentTemplate::create([
        'code' => 'TMP-C2',
        'name' => 'Template',
        'status' => 'draft',
        'visibility' => 'internal',
        'payload_snapshot_policy' => 'off',
    ]);

    DocumentPreset::create([
        'template_id' => $template->id,
        'key' => 'organization_name',
        'label' => 'Organization Name',
        'type' => 'text',
        'value' => ['text' => 'Org A'],
        'is_active' => true,
    ]);

    expect(
        fn() => DocumentPreset::create([
            'template_id' => $template->id,
            'key' => 'organization_name',
            'label' => 'Organization Name',
            'type' => 'text',
            'value' => ['text' => 'Org B'],
            'is_active' => true,
        ]),
    )->toThrow(UniqueConstraintViolationException::class);
});

it('enforces unique template field key per template', function () {
    $template = DocumentTemplate::create([
        'code' => 'TMP-C3',
        'name' => 'Template',
        'status' => 'draft',
        'visibility' => 'internal',
        'payload_snapshot_policy' => 'off',
    ]);

    DocumentTemplateField::create([
        'template_id' => $template->id,
        'label' => 'Name',
        'key' => 'name',
        'type' => 'text',
        'placeholder_tag' => '[doc.name]',
        'required' => false,
    ]);

    expect(
        fn() => DocumentTemplateField::create([
            'template_id' => $template->id,
            'label' => 'Name 2',
            'key' => 'name',
            'type' => 'text',
            'placeholder_tag' => '[doc.name_2]',
            'required' => false,
        ]),
    )->toThrow(UniqueConstraintViolationException::class);
});

it('enforces unique template field placeholder tag per template', function () {
    $template = DocumentTemplate::create([
        'code' => 'TMP-C4',
        'name' => 'Template',
        'status' => 'draft',
        'visibility' => 'internal',
        'payload_snapshot_policy' => 'off',
    ]);

    DocumentTemplateField::create([
        'template_id' => $template->id,
        'label' => 'Name',
        'key' => 'name',
        'type' => 'text',
        'placeholder_tag' => '[doc.name]',
        'required' => false,
    ]);

    expect(
        fn() => DocumentTemplateField::create([
            'template_id' => $template->id,
            'label' => 'Name 2',
            'key' => 'name_2',
            'type' => 'text',
            'placeholder_tag' => '[doc.name]',
            'required' => false,
        ]),
    )->toThrow(UniqueConstraintViolationException::class);
});

it('enforces unique template code', function () {
    DocumentTemplate::create([
        'code' => 'TMP-C5',
        'name' => 'Template',
        'status' => 'draft',
        'visibility' => 'internal',
        'payload_snapshot_policy' => 'off',
    ]);

    expect(
        fn() => DocumentTemplate::create([
            'code' => 'TMP-C5',
            'name' => 'Template 2',
            'status' => 'draft',
            'visibility' => 'internal',
            'payload_snapshot_policy' => 'off',
        ]),
    )->toThrow(UniqueConstraintViolationException::class);
});

it('enforces unique template category code', function () {
    DocumentTemplateCategory::create([
        'code' => 'CAT-C1',
        'name' => 'Category',
    ]);

    expect(
        fn() => DocumentTemplateCategory::create([
            'code' => 'CAT-C1',
            'name' => 'Category 2',
        ]),
    )->toThrow(UniqueConstraintViolationException::class);
});
