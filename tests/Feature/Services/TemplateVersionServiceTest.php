<?php

use Arseno25\DocxBuilder\Models\DocumentTemplate;
use Arseno25\DocxBuilder\Models\DocumentTemplateField;
use Arseno25\DocxBuilder\Models\DocumentTemplateVersion;
use Arseno25\DocxBuilder\Services\TemplateVersionService;

it('sets active template version and keeps it unique', function () {
    $template = DocumentTemplate::create([
        'code' => 'TMP-A',
        'name' => 'Template A',
        'status' => 'draft',
        'visibility' => 'internal',
        'payload_snapshot_policy' => 'off',
    ]);

    $v1 = DocumentTemplateVersion::create([
        'template_id' => $template->id,
        'version' => 'v1',
        'is_active' => false,
        'source_disk' => 'local',
        'source_path' => 'templates/a.docx',
    ]);

    $v2 = DocumentTemplateVersion::create([
        'template_id' => $template->id,
        'version' => 'v2',
        'is_active' => false,
        'source_disk' => 'local',
        'source_path' => 'templates/a2.docx',
    ]);

    app(TemplateVersionService::class)->setActive($template, $v1);

    $template->refresh();
    $v1->refresh();
    $v2->refresh();

    expect($template->active_version_id)->toBe($v1->id);
    expect($v1->is_active)->toBeTrue();
    expect($v2->is_active)->toBeFalse();

    app(TemplateVersionService::class)->setActive($template, $v2);

    $template->refresh();
    $v1->refresh();
    $v2->refresh();

    expect($template->active_version_id)->toBe($v2->id);
    expect($v1->is_active)->toBeFalse();
    expect($v2->is_active)->toBeTrue();
});

it('rolls back to the previous version', function () {
    $template = DocumentTemplate::create([
        'code' => 'TMP-ROLL',
        'name' => 'Template',
        'status' => 'draft',
        'visibility' => 'internal',
        'payload_snapshot_policy' => 'off',
    ]);

    $v1 = DocumentTemplateVersion::create([
        'template_id' => $template->id,
        'version' => 'v1',
        'is_active' => false,
        'source_disk' => 'local',
        'source_path' => 'templates/v1.docx',
    ]);

    $v2 = DocumentTemplateVersion::create([
        'template_id' => $template->id,
        'version' => 'v2',
        'is_active' => false,
        'source_disk' => 'local',
        'source_path' => 'templates/v2.docx',
    ]);

    $service = app(TemplateVersionService::class);
    $service->setActive($template, $v2);

    $rolledBackTo = $service->rollbackToPrevious($template);

    $template->refresh();
    $v1->refresh();
    $v2->refresh();

    expect($rolledBackTo->id)->toBe($v1->id);
    expect($template->active_version_id)->toBe($v1->id);
    expect($v1->is_active)->toBeTrue();
    expect($v2->is_active)->toBeFalse();
});

it('captures a schema snapshot when creating a version', function () {
    $template = DocumentTemplate::create([
        'code' => 'TMP-SNAP',
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
    ]);

    $service = app(TemplateVersionService::class);

    $version = $service->createVersion($template, [
        'version' => 'v1',
        'is_active' => true,
        'source_disk' => 'local',
        'source_path' => 'templates/v1.docx',
    ]);

    expect($version->schema_snapshot)->toBeArray();
    expect(collect($version->schema_snapshot)->pluck('key')->all())->toContain(
        'name',
    );
});
