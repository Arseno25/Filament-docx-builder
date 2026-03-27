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

it('applies transform rules before rendering (PRD 15.3 transform rules)', function () {
    $docx = DocxFixtureFactory::minimalTextDocx(
        '<w:p><w:r><w:t>Hello {{doc.name}}</w:t></w:r></w:p>',
    );
    Storage::disk('local')->put('templates/source.docx', $docx);

    $template = DocumentTemplate::create([
        'code' => 'TMP-TR1',
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
        'label' => 'Name',
        'key' => 'name',
        'type' => 'text',
        'placeholder_tag' => '[doc.name]',
        'required' => false,
        'transform_rules' => [
            ['type' => 'trim'],
            ['type' => 'upper'],
        ],
    ]);

    Livewire::test(GenerateDocument::class)
        ->set('data.template_id', $template->id)
        ->set('data.live_preview.enabled', true)
        ->set('data.fields', ['name' => '  john  '])
        ->assertSee('Hello JOHN', false);
});
