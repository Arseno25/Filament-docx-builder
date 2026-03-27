<?php

use Arseno25\DocxBuilder\Filament\Resources\DocumentGenerations\Pages\ListDocumentGenerations;
use Arseno25\DocxBuilder\Models\DocumentGeneration;
use Arseno25\DocxBuilder\Models\DocumentTemplate;
use Arseno25\DocxBuilder\Models\DocumentTemplateVersion;
use Arseno25\DocxBuilder\Rendering\RendererInterface;
use Arseno25\DocxBuilder\Tests\Support\FakeRenderer;
use Arseno25\DocxBuilder\Support\DocxBuilderPermissions;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    loginWithPermissions([
        DocxBuilderPermissions::GENERATIONS_VIEW_ANY,
        DocxBuilderPermissions::GENERATIONS_VIEW,
        DocxBuilderPermissions::GENERATIONS_DOWNLOAD,
        DocxBuilderPermissions::GENERATIONS_RETRY,
    ]);

    Storage::fake('local');

    $this->app->instance(RendererInterface::class, new FakeRenderer('DOCX'));
});

it(
    'shows download action only for successful generations (PRD 15.8 generation history)',
    function () {
        $template = DocumentTemplate::create([
            'code' => 'TMP-H1',
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

        $success = DocumentGeneration::create([
            'template_id' => $template->id,
            'template_version_id' => $version->id,
            'mode' => 'final',
            'status' => 'success',
            'output_disk' => 'local',
            'output_path' => 'docx-builder/out/success.docx',
            'output_filename' => 'success.docx',
        ]);

        $failed = DocumentGeneration::create([
            'template_id' => $template->id,
            'template_version_id' => $version->id,
            'mode' => 'final',
            'status' => 'failed',
            'output_disk' => 'local',
            'output_path' => 'docx-builder/out/failed.docx',
            'output_filename' => 'failed.docx',
            'error_message' => 'fail',
        ]);

        Livewire::test(ListDocumentGenerations::class)
            ->assertTableActionVisible('download', $success->getKey())
            ->assertTableActionHidden('download', $failed->getKey());
    },
);

it(
    'can trigger download action for successful generations (PRD 15.8 generation history)',
    function () {
        $template = DocumentTemplate::create([
            'code' => 'TMP-H2',
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

        $generation = DocumentGeneration::create([
            'template_id' => $template->id,
            'template_version_id' => $version->id,
            'mode' => 'final',
            'status' => 'success',
            'output_disk' => 'local',
            'output_path' => 'docx-builder/out/download.docx',
            'output_filename' => 'download.docx',
        ]);

        Storage::disk('local')->put($generation->output_path, 'DOCX');

        Livewire::test(ListDocumentGenerations::class)->callTableAction(
            'download',
            $generation->getKey(),
        );
    },
);

it(
    'shows retry action only for failed generations with a payload snapshot (PRD v1.5 retry failed generation)',
    function () {
        $template = DocumentTemplate::create([
            'code' => 'TMP-H3',
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

        $withSnapshot = DocumentGeneration::create([
            'template_id' => $template->id,
            'template_version_id' => $version->id,
            'mode' => 'final',
            'status' => 'failed',
            'output_disk' => 'local',
            'output_path' => 'docx-builder/out/failed-a.docx',
            'output_filename' => 'failed-a.docx',
            'payload_snapshot' => ['doc' => ['a' => 'b']],
            'error_message' => 'fail',
        ]);

        $withoutSnapshot = DocumentGeneration::create([
            'template_id' => $template->id,
            'template_version_id' => $version->id,
            'mode' => 'final',
            'status' => 'failed',
            'output_disk' => 'local',
            'output_path' => 'docx-builder/out/failed-b.docx',
            'output_filename' => 'failed-b.docx',
            'payload_snapshot' => null,
            'error_message' => 'fail',
        ]);

        $success = DocumentGeneration::create([
            'template_id' => $template->id,
            'template_version_id' => $version->id,
            'mode' => 'final',
            'status' => 'success',
            'output_disk' => 'local',
            'output_path' => 'docx-builder/out/success-a.docx',
            'output_filename' => 'success-a.docx',
        ]);

        Livewire::test(ListDocumentGenerations::class)
            ->assertTableActionVisible('retry', $withSnapshot->getKey())
            ->assertTableActionHidden('retry', $withoutSnapshot->getKey())
            ->assertTableActionHidden('retry', $success->getKey());
    },
);

it('can trigger retry action and creates a new generation record', function () {
    config()->set('docx-builder.queue.enabled', false);
    config()->set('docx-builder.output_disk', 'local');
    config()->set('docx-builder.output_path_prefix', 'docx-builder');

    $template = DocumentTemplate::create([
        'code' => 'TMP-H4',
        'name' => 'Template',
        'status' => 'draft',
        'visibility' => 'internal',
        'payload_snapshot_policy' => 'off',
        'output_filename_pattern' => 'R_{doc.a}',
    ]);

    $version = DocumentTemplateVersion::create([
        'template_id' => $template->id,
        'version' => 'v1',
        'is_active' => true,
        'source_disk' => 'local',
        'source_path' => 'templates/source.docx',
    ]);

    $failed = DocumentGeneration::create([
        'template_id' => $template->id,
        'template_version_id' => $version->id,
        'mode' => 'final',
        'status' => 'failed',
        'output_disk' => 'local',
        'output_path' => 'docx-builder/out/failed-c.docx',
        'output_filename' => 'failed-c.docx',
        'payload_snapshot' => ['doc' => ['a' => 'b']],
        'error_message' => 'fail',
    ]);

    expect(DocumentGeneration::query()->count())->toBe(1);

    Livewire::test(ListDocumentGenerations::class)->callTableAction(
        'retry',
        $failed->getKey(),
    );

    expect(DocumentGeneration::query()->count())->toBe(2);
    $new = DocumentGeneration::query()->latest('id')->firstOrFail();
    expect($new->status)->toBe('success');
});
