<?php

use Arseno25\DocxBuilder\Jobs\RenderDocumentGeneration;
use Arseno25\DocxBuilder\Models\DocumentGeneration;
use Arseno25\DocxBuilder\Models\DocumentTemplate;
use Arseno25\DocxBuilder\Models\DocumentTemplateVersion;
use Arseno25\DocxBuilder\Rendering\RendererInterface;
use Arseno25\DocxBuilder\Services\GenerationService;
use Arseno25\DocxBuilder\Tests\Support\FakeRenderer;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    config()->set('docx-builder.output_disk', 'local');
    config()->set('docx-builder.output_path_prefix', 'docx-builder');

    $this->app->instance(RendererInterface::class, new FakeRenderer('DOCX'));
});

it('queues a generation and the job can render it (PRD v1.5 queue + retry)', function () {
    Queue::fake();

    $template = DocumentTemplate::create([
        'code' => 'TMP-Q1',
        'name' => 'Template',
        'status' => 'draft',
        'visibility' => 'internal',
        'payload_snapshot_policy' => 'always',
        'output_filename_pattern' => 'OUT_{doc.id}',
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

    $payload = ['doc' => ['id' => 'X']];

    $generation = app(GenerationService::class)->queue(
        $template,
        $payload,
        'final',
        ['queued' => true],
    );

    expect($generation->status)->toBe('queued');

    Queue::assertPushed(RenderDocumentGeneration::class, function (RenderDocumentGeneration $job) use ($generation) {
        return $job->generationId === $generation->id;
    });

    (new RenderDocumentGeneration($generation->id, $payload, ['queued' => true]))->handle(
        app(GenerationService::class),
    );

    $fresh = DocumentGeneration::query()->findOrFail($generation->id);

    expect($fresh->status)->toBe('success');
    expect(Storage::disk('local')->exists($fresh->output_path))->toBeTrue();
});
