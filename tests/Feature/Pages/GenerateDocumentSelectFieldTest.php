<?php

use Arseno25\DocxBuilder\Filament\Pages\GenerateDocument;
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
});

afterEach(function () {
    Carbon::setTestNow();
});

it('renders select field options in the generate page', function () {
    $docx = DocxFixtureFactory::minimalTextDocx(
        '<w:p><w:r><w:t>Department: {{doc.department}}</w:t></w:r></w:p>',
    );
    Storage::disk('local')->put('templates/source.docx', $docx);

    $template = DocumentTemplate::create([
        'code' => 'TMP-SEL1',
        'name' => 'Template',
        'status' => 'draft',
        'visibility' => 'internal',
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
        'label' => 'Department',
        'key' => 'department',
        'type' => 'select',
        'placeholder_tag' => '[doc.department]',
        'required' => false,
        'data_source_config' => [
            'options' => [
                'HR' => 'HR',
                'Finance' => 'Finance',
            ],
        ],
    ]);

    Livewire::test(GenerateDocument::class)
        ->set('data.template_id', $template->id)
        ->set('data.live_preview.enabled', true)
        ->set('data.fields', ['department' => 'HR'])
        ->assertSee('Department: HR', false)
        ->assertSee('Finance', false);
});
