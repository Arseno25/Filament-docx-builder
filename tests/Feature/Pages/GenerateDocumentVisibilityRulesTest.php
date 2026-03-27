<?php

use Arseno25\DocxBuilder\Filament\Pages\GenerateDocument;
use Arseno25\DocxBuilder\Models\DocumentGeneration;
use Arseno25\DocxBuilder\Models\DocumentTemplate;
use Arseno25\DocxBuilder\Models\DocumentTemplateField;
use Arseno25\DocxBuilder\Models\DocumentTemplateVersion;
use Arseno25\DocxBuilder\Rendering\RendererInterface;
use Arseno25\DocxBuilder\Support\DocxBuilderPermissions;
use Arseno25\DocxBuilder\Tests\Support\FakeRenderer;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    loginWithPermissions([DocxBuilderPermissions::GENERATE]);

    Storage::fake('local');
    config()->set('docx-builder.template_disk', 'local');
    config()->set('docx-builder.output_disk', 'local');
    config()->set('docx-builder.output_path_prefix', 'docx-builder');

    $this->app->instance(RendererInterface::class, new FakeRenderer('DOCX'));
});

it('removes hidden fields from the payload based on visibility rules (PRD 15.4)', function () {
    $template = DocumentTemplate::create([
        'code' => 'TMP-VIS1',
        'name' => 'Template',
        'status' => 'draft',
        'visibility' => 'internal',
        'payload_snapshot_policy' => 'always',
        'output_filename_pattern' => 'OUT_{doc.type}',
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
        'label' => 'Type',
        'key' => 'type',
        'type' => 'text',
        'placeholder_tag' => '[doc.type]',
        'required' => false,
    ]);

    DocumentTemplateField::create([
        'template_id' => $template->id,
        'label' => 'Extra',
        'key' => 'extra',
        'type' => 'text',
        'placeholder_tag' => '[doc.extra]',
        'required' => false,
        'visibility_rules' => [
            'when' => 'type',
            'operator' => 'equals',
            'value' => 'yes',
        ],
    ]);

    Livewire::test(GenerateDocument::class)
        ->set('data.template_id', $template->id)
        ->set('data.mode', 'test')
        ->set('data.fields', [
            'type' => 'no',
            'extra' => 'SHOULD_NOT_BE_INCLUDED',
        ])
        ->call('submit', 'test');

    $generation = DocumentGeneration::query()->latest('id')->firstOrFail();

    expect($generation->payload_snapshot['doc'])->not->toHaveKey('extra');
});
