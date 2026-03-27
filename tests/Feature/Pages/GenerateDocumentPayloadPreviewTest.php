<?php

use Arseno25\DocxBuilder\Filament\Pages\GenerateDocument;
use Arseno25\DocxBuilder\Models\DocumentTemplate;
use Arseno25\DocxBuilder\Models\DocumentTemplateField;
use Arseno25\DocxBuilder\Support\DocxBuilderPermissions;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    Carbon::setTestNow(Carbon::create(2026, 3, 27, 10, 0, 0));

    loginWithPermissions([DocxBuilderPermissions::GENERATE]);
});

afterEach(function () {
    Carbon::setTestNow();
});

it('shows a payload preview with applied default values (PRD v1.5 cleaner payload preview)', function () {
    $template = DocumentTemplate::create([
        'code' => 'TMP-PP1',
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
        'default_value' => ['value' => 'Alice'],
    ]);

    Livewire::test(GenerateDocument::class)
        ->set('data.template_id', $template->id)
        ->assertSee('Alice');
});
