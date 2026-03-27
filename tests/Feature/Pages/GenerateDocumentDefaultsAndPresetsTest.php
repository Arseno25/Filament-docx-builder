<?php

use Arseno25\DocxBuilder\Filament\Pages\GenerateDocument;
use Arseno25\DocxBuilder\Models\DocumentGeneration;
use Arseno25\DocxBuilder\Models\DocumentPreset;
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

it(
    'applies field default values into the generation payload (PRD 15.4 dynamic generation form)',
    function () {
        $template = DocumentTemplate::create([
            'code' => 'TMP-DP1',
            'name' => 'Template',
            'status' => 'draft',
            'visibility' => 'internal',
            'payload_snapshot_policy' => 'always',
            'output_filename_pattern' => 'OUT_{doc.name}',
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
            'default_value' => ['value' => 'Default Name'],
        ]);

        Livewire::test(GenerateDocument::class)
            ->set('data.template_id', $template->id)
            ->set('data.mode', 'test')
            ->set('data.apply_presets', false)
            ->call('submit', 'test');

        $generation = DocumentGeneration::query()->latest('id')->firstOrFail();

        expect($generation->payload_snapshot['doc']['name'])->toBe(
            'Default Name',
        );
    },
);

it('applies active presets into empty fields (PRD 15.10 presets)', function () {
    $template = DocumentTemplate::create([
        'code' => 'TMP-DP2',
        'name' => 'Template',
        'status' => 'draft',
        'visibility' => 'internal',
        'payload_snapshot_policy' => 'always',
        'output_filename_pattern' => 'OUT_{doc.org_name}',
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
        'label' => 'Organization Name',
        'key' => 'org_name',
        'type' => 'text',
        'placeholder_tag' => '[doc.org_name]',
        'required' => false,
    ]);

    DocumentPreset::create([
        'template_id' => $template->id,
        'key' => 'org_name',
        'label' => 'Organization Name',
        'type' => 'text',
        'value' => ['text' => 'ACME'],
        'is_active' => true,
        'sort_order' => 0,
    ]);

    Livewire::test(GenerateDocument::class)
        ->set('data.template_id', $template->id)
        ->set('data.mode', 'test')
        ->set('data.apply_presets', true)
        ->call('submit', 'test');

    $generation = DocumentGeneration::query()->latest('id')->firstOrFail();

    expect($generation->payload_snapshot['doc']['org_name'])->toBe('ACME');
});
