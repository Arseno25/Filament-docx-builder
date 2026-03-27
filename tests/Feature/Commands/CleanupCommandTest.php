<?php

use Arseno25\DocxBuilder\Models\DocumentGeneration;
use Arseno25\DocxBuilder\Models\DocumentTemplate;
use Arseno25\DocxBuilder\Models\DocumentTemplateVersion;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    config()->set('docx-builder.output_disk', 'local');
    config()->set('docx-builder.output_path_prefix', 'docx-builder');
    config()->set('docx-builder.retention_days.test', 1);
    config()->set('docx-builder.retention_days.final', null);
});

it('deletes old test generations and their files (PRD retention cleanup)', function () {
    Carbon::setTestNow(Carbon::create(2026, 3, 27, 10, 0, 0));

    $template = DocumentTemplate::create([
        'code' => 'TMP-CL1',
        'name' => 'Template',
        'status' => 'draft',
        'visibility' => 'internal',
        'payload_snapshot_policy' => 'off',
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

    $old = DocumentGeneration::create([
        'template_id' => $template->id,
        'template_version_id' => $version->id,
        'mode' => 'test',
        'status' => 'success',
        'output_disk' => 'local',
        'output_path' => 'docx-builder/out/old.docx',
        'output_filename' => 'old.docx',
        'finished_at' => Carbon::now()->subDays(2),
    ]);

    Storage::disk('local')->put($old->output_path, 'DOCX');

    Artisan::call('docx-builder:cleanup', ['--mode' => 'test']);

    expect(DocumentGeneration::query()->whereKey($old->getKey())->exists())->toBeFalse();
    expect(Storage::disk('local')->exists('docx-builder/out/old.docx'))->toBeFalse();
});

it('does not delete final generations when retention is null', function () {
    Carbon::setTestNow(Carbon::create(2026, 3, 27, 10, 0, 0));

    $template = DocumentTemplate::create([
        'code' => 'TMP-CL2',
        'name' => 'Template',
        'status' => 'draft',
        'visibility' => 'internal',
        'payload_snapshot_policy' => 'off',
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

    $final = DocumentGeneration::create([
        'template_id' => $template->id,
        'template_version_id' => $version->id,
        'mode' => 'final',
        'status' => 'success',
        'output_disk' => 'local',
        'output_path' => 'docx-builder/out/final.docx',
        'output_filename' => 'final.docx',
        'finished_at' => Carbon::now()->subDays(200),
    ]);

    Storage::disk('local')->put($final->output_path, 'DOCX');

    Artisan::call('docx-builder:cleanup', ['--mode' => 'final']);

    expect(DocumentGeneration::query()->whereKey($final->getKey())->exists())->toBeTrue();
    expect(Storage::disk('local')->exists('docx-builder/out/final.docx'))->toBeTrue();
});
