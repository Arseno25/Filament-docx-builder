<?php

use Arseno25\DocxBuilder\Models\DocumentTemplate;
use Arseno25\DocxBuilder\Models\DocumentTemplateField;
use Arseno25\DocxBuilder\Models\DocumentTemplateVersion;
use Arseno25\DocxBuilder\Rendering\RendererInterface;
use Arseno25\DocxBuilder\Tests\Support\FakeRenderer;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    config()->set('docx-builder.output_disk', 'local');
    config()->set('docx-builder.output_path_prefix', 'docx-builder');

    config()->set('docx-builder.api.enabled', true);
    config()->set('docx-builder.api.prefix', 'docx-builder');
    config()->set('docx-builder.api.middleware', []);
    config()->set('docx-builder.api.token', 'secret');

    $this->app->instance(RendererInterface::class, new FakeRenderer('DOCX'));
});

it('rejects API requests without a token', function () {
    $this->postJson('/docx-builder/generations', [])->assertStatus(401);
});

it('creates a generation via the API and returns links', function () {
    $template = DocumentTemplate::create([
        'code' => 'TMP-API1',
        'name' => 'Template',
        'status' => 'draft',
        'visibility' => 'internal',
        'payload_snapshot_policy' => 'always',
        'output_filename_pattern' => 'API_{doc.name}',
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

    $res = $this
        ->withHeader('X-Docx-Builder-Token', 'secret')
        ->postJson('/docx-builder/generations', [
            'template_id' => $template->id,
            'mode' => 'final',
            'fields' => ['name' => 'John'],
        ])
        ->assertStatus(201)
        ->assertJsonPath('status', 'success');

    $id = $res->json('id');
    expect($id)->not()->toBeNull();

    $this
        ->withHeader('X-Docx-Builder-Token', 'secret')
        ->getJson("/docx-builder/generations/{$id}")
        ->assertStatus(200)
        ->assertJsonPath('id', $id);

    $this
        ->withHeader('X-Docx-Builder-Token', 'secret')
        ->get("/docx-builder/generations/{$id}/download")
        ->assertStatus(200);
});

it('returns validation errors when required fields are missing', function () {
    $template = DocumentTemplate::create([
        'code' => 'TMP-API2',
        'name' => 'Template',
        'status' => 'draft',
        'visibility' => 'internal',
        'payload_snapshot_policy' => 'always',
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
        'required' => true,
    ]);

    $this
        ->withHeader('X-Docx-Builder-Token', 'secret')
        ->postJson('/docx-builder/generations', [
            'template_id' => $template->id,
            'mode' => 'final',
            'fields' => ['name' => ''],
        ])
        ->assertStatus(422)
        ->assertJsonPath('message', 'Some required fields are missing.')
        ->assertJsonPath('missing.0', 'name');
});
