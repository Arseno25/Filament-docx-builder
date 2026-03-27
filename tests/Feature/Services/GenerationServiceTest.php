<?php

use Arseno25\DocxBuilder\Models\DocumentGeneration;
use Arseno25\DocxBuilder\Models\DocumentTemplate;
use Arseno25\DocxBuilder\Models\DocumentTemplateVersion;
use Arseno25\DocxBuilder\Rendering\RendererInterface;
use Arseno25\DocxBuilder\Services\GenerationService;
use Arseno25\DocxBuilder\Tests\Support\FakeRenderer;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    config()->set('docx-builder.output_disk', 'local');
    config()->set('docx-builder.output_path_prefix', 'docx-builder');
});

it('rejects archived templates', function () {
    $this->app->instance(RendererInterface::class, new FakeRenderer());

    $template = DocumentTemplate::create([
        'code' => 'TMP-G1',
        'name' => 'Template',
        'status' => 'draft',
        'visibility' => 'internal',
        'is_archived' => true,
        'payload_snapshot_policy' => 'off',
    ]);

    app(GenerationService::class)->generate($template, ['doc' => []], 'final');
})->throws(RuntimeException::class, 'Template is archived.');

it('rejects templates without an active version', function () {
    $this->app->instance(RendererInterface::class, new FakeRenderer());

    $template = DocumentTemplate::create([
        'code' => 'TMP-G2',
        'name' => 'Template',
        'status' => 'draft',
        'visibility' => 'internal',
        'payload_snapshot_policy' => 'off',
    ]);

    app(GenerationService::class)->generate($template, ['doc' => []], 'final');
})->throws(RuntimeException::class, 'Template has no active version.');

it('stores generated docx and records history success', function () {
    $this->app->instance(RendererInterface::class, new FakeRenderer('DOCX'));

    $template = DocumentTemplate::create([
        'code' => 'TMP-G3',
        'name' => 'Template',
        'status' => 'draft',
        'visibility' => 'internal',
        'payload_snapshot_policy' => 'off',
        'output_filename_pattern' => 'INV_{doc.number}',
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

    $generation = app(GenerationService::class)->generate(
        $template,
        [
            'doc' => ['number' => 'X-1'],
        ],
        'final',
    );

    expect($generation)->toBeInstanceOf(DocumentGeneration::class);
    expect($generation->status)->toBe('success');
    expect(
        Storage::disk('local')->exists($generation->output_path),
    )->toBeTrue();
});

it('captures render error and marks generation failed', function () {
    $this->app->instance(
        RendererInterface::class,
        new FakeRenderer('x', shouldThrow: true),
    );

    $template = DocumentTemplate::create([
        'code' => 'TMP-G4',
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

    try {
        app(GenerationService::class)->generate(
            $template,
            ['doc' => []],
            'final',
        );
    } catch (Throwable) {
        // ignore
    }

    $gen = DocumentGeneration::query()
        ->where('template_id', $template->id)
        ->firstOrFail();
    expect($gen->status)->toBe('failed');
    expect($gen->error_message)->toBe('Renderer failed.');
});

it('respects payload snapshot policy', function () {
    $this->app->instance(RendererInterface::class, new FakeRenderer('DOCX'));

    $template = DocumentTemplate::create([
        'code' => 'TMP-G5',
        'name' => 'Template',
        'status' => 'draft',
        'visibility' => 'internal',
        'payload_snapshot_policy' => 'on_success',
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

    $payload = ['doc' => ['a' => 'b']];
    $generation = app(GenerationService::class)->generate(
        $template,
        $payload,
        'final',
    );

    expect($generation->payload_snapshot)->toBe($payload);
});

it(
    'can retry a failed generation when payload snapshot exists (PRD v1.5 retry failed generation)',
    function () {
        config()->set('docx-builder.queue.enabled', false);

        $this->app->instance(
            RendererInterface::class,
            new FakeRenderer('x', shouldThrow: true),
        );

        $template = DocumentTemplate::create([
            'code' => 'TMP-G6',
            'name' => 'Template',
            'status' => 'draft',
            'visibility' => 'internal',
            'payload_snapshot_policy' => 'always',
            'output_filename_pattern' => 'R_{doc.number}',
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

        try {
            app(GenerationService::class)->generate(
                $template,
                [
                    'doc' => ['number' => 'X-2'],
                ],
                'final',
            );
        } catch (Throwable) {
            // ignore
        }

        $failed = DocumentGeneration::query()
            ->where('template_id', $template->id)
            ->firstOrFail();
        expect($failed->status)->toBe('failed');
        expect($failed->payload_snapshot)->toBeArray();
        expect($failed->payload_snapshot['doc']['number'])->toBe('X-2');

        $this->app->instance(
            RendererInterface::class,
            new FakeRenderer('DOCX'),
        );

        $new = app(GenerationService::class)->retryFailedGeneration($failed);

        expect($new->id)->not()->toBe($failed->id);
        expect($new->status)->toBe('success');
        expect($new->template_version_id)->toBe($failed->template_version_id);
        expect($new->render_log['retry_of'])->toBe($failed->id);
        expect(Storage::disk('local')->exists($new->output_path))->toBeTrue();
    },
);
