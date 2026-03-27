<?php

use Arseno25\DocxBuilder\Filament\Pages\GenerateDocument;
use Arseno25\DocxBuilder\Models\DocumentGeneration;
use Arseno25\DocxBuilder\Models\DocumentNumberSequence;
use Arseno25\DocxBuilder\Models\DocumentTemplate;
use Arseno25\DocxBuilder\Models\DocumentTemplateField;
use Arseno25\DocxBuilder\Models\DocumentTemplateVersion;
use Arseno25\DocxBuilder\Rendering\RendererInterface;
use Arseno25\DocxBuilder\Tests\Support\FakeRenderer;
use Arseno25\DocxBuilder\Support\DocxBuilderPermissions;
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

it('submits test generation without numbering injection', function () {
    $template = DocumentTemplate::create([
        'code' => 'TMP-P1',
        'name' => 'Template',
        'status' => 'draft',
        'visibility' => 'internal',
        'payload_snapshot_policy' => 'always',
        'output_filename_pattern' => 'TEST_{doc.document_number}',
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
        'label' => 'Document Number',
        'key' => 'document_number',
        'type' => 'text',
        'placeholder_tag' => '[doc.document_number]',
        'required' => false,
    ]);

    DocumentTemplateField::create([
        'template_id' => $template->id,
        'label' => 'Name',
        'key' => 'name',
        'type' => 'text',
        'placeholder_tag' => '[doc.name]',
        'required' => false,
    ]);

    DocumentNumberSequence::create([
        'template_id' => $template->id,
        'key' => 'document_number',
        'pattern' => '{seq:3}/SKD/{roman_month}/{year}',
        'counter' => 0,
        'reset_policy' => 'never',
        'is_active' => true,
    ]);

    Livewire::test(GenerateDocument::class)
        ->set('data.template_id', $template->id)
        ->set('data.mode', 'test')
        ->set('data.use_numbering', true)
        ->set(
            'data.sequence_id',
            DocumentNumberSequence::query()
                ->where('template_id', $template->id)
                ->value('id'),
        )
        ->set('data.fields', [
            'name' => '',
            'document_number' => '',
        ])
        ->call('submit', 'test');

    $generation = DocumentGeneration::query()->latest('id')->firstOrFail();

    expect($generation->mode)->toBe('test');
    expect($generation->status)->toBe('success');
    expect($generation->render_log['numbering'] ?? null)->toBeNull();
});

it(
    'submits final generation with numbering injection and warnings persisted',
    function () {
        $template = DocumentTemplate::create([
            'code' => 'TMP-P2',
            'name' => 'Template',
            'status' => 'draft',
            'visibility' => 'internal',
            'payload_snapshot_policy' => 'always',
            'output_filename_pattern' => 'FINAL_{doc.document_number}',
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
            'label' => 'Document Number',
            'key' => 'document_number',
            'type' => 'text',
            'placeholder_tag' => '[doc.document_number]',
            'required' => false,
        ]);

        DocumentTemplateField::create([
            'template_id' => $template->id,
            'label' => 'Name',
            'key' => 'name',
            'type' => 'text',
            'placeholder_tag' => '[doc.name]',
            'required' => false,
        ]);

        $sequence = DocumentNumberSequence::create([
            'template_id' => $template->id,
            'key' => 'document_number',
            'pattern' => '{seq:3}/SKD/{roman_month}/{year}',
            'counter' => 0,
            'reset_policy' => 'never',
            'is_active' => true,
        ]);

        Livewire::test(GenerateDocument::class)
            ->set('data.template_id', $template->id)
            ->set('data.mode', 'final')
            ->set('data.use_numbering', true)
            ->set('data.sequence_id', $sequence->id)
            ->set('data.fields', [
                'name' => '',
                'document_number' => '',
            ])
            ->call('submit', 'final');

        $generation = DocumentGeneration::query()->latest('id')->firstOrFail();

        expect($generation->mode)->toBe('final');
        expect($generation->status)->toBe('success');
        expect($generation->render_log['numbering']['applied'])->toBeTrue();
        expect($generation->payload_snapshot['doc']['document_number'])->toBe(
            '001/SKD/III/2026',
        );

        $warnings = $generation->render_log['warnings'] ?? [];
        expect($warnings)->toBeArray();
        expect(collect($warnings)->pluck('key')->all())->toContain('name');
    },
);
