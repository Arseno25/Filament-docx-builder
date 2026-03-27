<?php

use Arseno25\DocxBuilder\Filament\Pages\GenerateDocument;
use Arseno25\DocxBuilder\Models\DocumentTemplate;
use Arseno25\DocxBuilder\Models\DocumentTemplateField;
use Arseno25\DocxBuilder\Models\DocumentTemplateVersion;
use Arseno25\DocxBuilder\Tests\Support\DocxFixtureFactory;
use Arseno25\DocxBuilder\Tests\Support\Enums\TestDepartment;
use Arseno25\DocxBuilder\Tests\Support\Models\TestPerson;
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

it('supports enum-backed select options via data_source_type=enum', function () {
    $docx = DocxFixtureFactory::minimalTextDocx(
        '<w:p><w:r><w:t>Department: {{doc.department}}</w:t></w:r></w:p>',
    );
    Storage::disk('local')->put('templates/source.docx', $docx);

    $template = DocumentTemplate::create([
        'code' => 'TMP-DS1',
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
        'data_source_type' => 'enum',
        'data_source_config' => [
            'enum_class' => TestDepartment::class,
        ],
    ]);

    Livewire::test(GenerateDocument::class)
        ->set('data.template_id', $template->id)
        ->set('data.live_preview.enabled', true)
        ->set('data.fields', ['department' => 'HR'])
        ->assertSee('Department: HR', false)
        ->assertSee('Finance', false);
});

it('supports model-driven select options via data_source_type=model', function () {
    $docx = DocxFixtureFactory::minimalTextDocx(
        '<w:p><w:r><w:t>Person: {{doc.person_id}}</w:t></w:r></w:p>',
    );
    Storage::disk('local')->put('templates/source.docx', $docx);

    $template = DocumentTemplate::create([
        'code' => 'TMP-DS2',
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

    $jane = TestPerson::create(['name' => 'Jane']);
    $john = TestPerson::create(['name' => 'John']);

    DocumentTemplateField::create([
        'template_id' => $template->id,
        'label' => 'Person',
        'key' => 'person_id',
        'type' => 'select',
        'placeholder_tag' => '[doc.person_id]',
        'required' => false,
        'data_source_type' => 'model',
        'data_source_config' => [
            'model_class' => TestPerson::class,
            'value_attribute' => 'id',
            'label_attribute' => 'name',
            'order_by' => 'name',
            'limit' => 50,
        ],
    ]);

    Livewire::test(GenerateDocument::class)
        ->set('data.template_id', $template->id)
        ->set('data.live_preview.enabled', true)
        ->set('data.fields', ['person_id' => (string) $jane->id])
        ->assertSee('Person: ' . (string) $jane->id, false)
        ->assertSee('John', false)
        ->assertSee('Jane', false);
});
