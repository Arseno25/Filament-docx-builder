<?php

use Arseno25\DocxBuilder\Filament\Pages\GenerateDocument;
use Arseno25\DocxBuilder\Models\DocumentNumberSequence;
use Arseno25\DocxBuilder\Models\DocumentTemplate;
use Arseno25\DocxBuilder\Models\DocumentTemplateField;
use Arseno25\DocxBuilder\Models\DocumentTemplateVersion;
use Arseno25\DocxBuilder\Tests\Support\DocxFixtureFactory;
use Arseno25\DocxBuilder\Support\DocxBuilderPermissions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Carbon::setTestNow(Carbon::create(2026, 3, 27, 10, 0, 0));

    loginWithPermissions([DocxBuilderPermissions::GENERATE]);

    Storage::fake('local');
    config()->set('docx-builder.template_disk', 'local');
    config()->set('docx-builder.output_disk', 'local');
    config()->set('docx-builder.output_path_prefix', 'docx-builder');
});

afterEach(function () {
    Carbon::setTestNow();
});

it('renders a live preview with replaced placeholders', function () {
    $docx = DocxFixtureFactory::minimalTextDocx(
        '<w:p><w:r><w:t>Hello {{doc.name}}</w:t></w:r></w:p>',
    );
    Storage::disk('local')->put('templates/source.docx', $docx);

    $template = DocumentTemplate::create([
        'code' => 'TMP-LP1',
        'name' => 'Template',
        'status' => 'draft',
        'visibility' => 'internal',
        'output_filename_pattern' => 'TEST_{doc.name}',
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

    Livewire::test(GenerateDocument::class)
        ->set('data.template_id', $template->id)
        ->set('data.live_preview.enabled', true)
        ->set('data.fields', ['name' => 'John'])
        ->assertSee('Hello John', false);
});

it(
    'does not increment a sequence counter when previewing numbering',
    function () {
        $docx = DocxFixtureFactory::minimalTextDocx(
            '<w:p><w:r><w:t>No: {{doc.document_number}}</w:t></w:r></w:p>',
        );
        Storage::disk('local')->put('templates/source.docx', $docx);

        $template = DocumentTemplate::create([
            'code' => 'TMP-LP2',
            'name' => 'Template',
            'status' => 'draft',
            'visibility' => 'internal',
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
            ->set('data.live_preview.enabled', true)
            ->set('data.fields', ['document_number' => ''])
            ->assertSee('No: 001/SKD/III/2026', false);

        $sequence->refresh();
        expect((int) $sequence->counter)->toBe(0);
    },
);
