<?php

use Arseno25\DocxBuilder\Filament\Pages\GenerateDocument;
use Arseno25\DocxBuilder\Models\DocumentGeneration;
use Arseno25\DocxBuilder\Models\DocumentTemplate;
use Arseno25\DocxBuilder\Models\DocumentTemplateField;
use Arseno25\DocxBuilder\Models\DocumentTemplateVersion;
use Arseno25\DocxBuilder\Rendering\RendererInterface;
use Arseno25\DocxBuilder\Support\DocxBuilderPermissions;
use Arseno25\DocxBuilder\Tests\Support\FakeRenderer;
use Arseno25\DocxBuilder\Tests\Support\Models\TestPerson;
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
    'prefills payload from a source model record (PRD 15.11 basic model binding)',
    function () {
        $person = TestPerson::create(['name' => 'Bob']);

        $template = DocumentTemplate::create([
            'code' => 'TMP-MB1',
            'name' => 'Template',
            'status' => 'draft',
            'visibility' => 'internal',
            'payload_snapshot_policy' => 'always',
            'output_filename_pattern' => 'OUT_{doc.name}',
            'source_model_class' => TestPerson::class,
            'source_model_label_attribute' => 'name',
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
            'data_source_config' => ['attribute' => 'name'],
        ]);

        Livewire::test(GenerateDocument::class)
            ->set('data.template_id', $template->id)
            ->set('data.source_record_id', $person->getKey())
            ->set('data.mode', 'test')
            ->call('submit', 'test');

        $generation = DocumentGeneration::query()->latest('id')->firstOrFail();

        expect($generation->payload_snapshot['doc']['name'])->toBe('Bob');
        expect($generation->source_type)->toBe(TestPerson::class);
        expect($generation->source_id)->toBe((string) $person->getKey());
    },
);
