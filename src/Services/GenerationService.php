<?php

namespace Arseno25\DocxBuilder\Services;

use Arseno25\DocxBuilder\Jobs\RenderDocumentGeneration;
use Arseno25\DocxBuilder\Models\DocumentGeneration;
use Arseno25\DocxBuilder\Models\DocumentTemplate;
use Arseno25\DocxBuilder\Models\DocumentTemplateVersion;
use Arseno25\DocxBuilder\Rendering\RendererInterface;
use Arseno25\DocxBuilder\Support\FilenamePattern;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class GenerationService
{
    public function __construct(private readonly RendererInterface $renderer) {}

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $renderLog
     */
    public function generate(
        DocumentTemplate $template,
        array $payload,
        string $mode = 'final',
        array $renderLog = [],
        ?string $sourceType = null,
        ?string $sourceId = null,
    ): DocumentGeneration {
        if ($template->is_archived) {
            throw new RuntimeException('Template is archived.');
        }

        $version = $template->activeVersion()->first();
        if (!$version) {
            throw new RuntimeException('Template has no active version.');
        }

        [
            $outputDisk,
            $relativePath,
            $outputFilename,
        ] = $this->makeOutputPathAndName($template, $payload);

        $generation = new DocumentGeneration([
            'template_id' => $template->id,
            'template_version_id' => $version->id,
            'mode' => $mode,
            'status' => 'running',
            'output_disk' => $outputDisk,
            'output_path' => $relativePath,
            'output_filename' => $outputFilename,
            'user_id' => Auth::id(),
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'render_log' => $renderLog,
            'started_at' => now(),
        ]);

        $payloadSnapshotPolicy =
            (string) ($template->payload_snapshot_policy ?:
            config('docx-builder.payload_snapshot_policy', 'off'));
        if ($payloadSnapshotPolicy === 'always') {
            $generation->payload_snapshot = $payload;
        }

        $generation->save();

        try {
            $bytes = $this->renderer->render($version, $payload);
            Storage::disk($outputDisk)->put($relativePath, $bytes);

            $generation->status = 'success';
            $generation->finished_at = now();

            if ($payloadSnapshotPolicy === 'on_success') {
                $generation->payload_snapshot = $payload;
            }

            $generation->save();
        } catch (\Throwable $e) {
            $generation->status = 'failed';
            $generation->error_message = $e->getMessage();
            $generation->finished_at = now();

            if ($payloadSnapshotPolicy === 'on_success') {
                $generation->payload_snapshot = null;
            }

            $generation->save();

            throw $e;
        }

        return $generation->fresh();
    }

    /**
     * Queue a generation and return the created history record.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $renderLog
     */
    public function queue(
        DocumentTemplate $template,
        array $payload,
        string $mode = 'final',
        array $renderLog = [],
        ?string $sourceType = null,
        ?string $sourceId = null,
    ): DocumentGeneration {
        if ($template->is_archived) {
            throw new RuntimeException('Template is archived.');
        }

        $version = $template->activeVersion()->first();
        if (!$version) {
            throw new RuntimeException('Template has no active version.');
        }

        [
            $outputDisk,
            $relativePath,
            $outputFilename,
        ] = $this->makeOutputPathAndName($template, $payload);

        $generation = new DocumentGeneration([
            'template_id' => $template->id,
            'template_version_id' => $version->id,
            'mode' => $mode,
            'status' => 'queued',
            'output_disk' => $outputDisk,
            'output_path' => $relativePath,
            'output_filename' => $outputFilename,
            'user_id' => Auth::id(),
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'render_log' => $renderLog,
        ]);

        $generation->save();

        $dispatch = RenderDocumentGeneration::dispatch(
            $generation->id,
            $payload,
            $renderLog,
        );

        $connection = config('docx-builder.queue.connection');
        if (filled($connection)) {
            $dispatch->onConnection((string) $connection);
        }

        $queue = config('docx-builder.queue.queue');
        if (filled($queue)) {
            $dispatch->onQueue((string) $queue);
        }

        return $generation->fresh();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $renderLog
     */
    public function renderQueuedGeneration(
        int $generationId,
        array $payload,
        array $renderLog = [],
    ): void {
        /** @var DocumentGeneration $generation */
        $generation = DocumentGeneration::query()->findOrFail($generationId);

        if ($generation->status === 'success') {
            return;
        }

        $template = DocumentTemplate::query()->findOrFail(
            $generation->template_id,
        );
        $version = DocumentTemplateVersion::query()->findOrFail(
            $generation->template_version_id,
        );

        $generation->status = 'running';
        $generation->render_log = $renderLog ?: $generation->render_log ?? [];
        $generation->started_at = now();
        $generation->save();

        $payloadSnapshotPolicy =
            (string) ($template->payload_snapshot_policy ?:
            config('docx-builder.payload_snapshot_policy', 'off'));

        if ($payloadSnapshotPolicy === 'always') {
            $generation->payload_snapshot = $payload;
        }

        try {
            $bytes = $this->renderer->render($version, $payload);
            Storage::disk($generation->output_disk)->put(
                $generation->output_path,
                $bytes,
            );

            $generation->status = 'success';
            $generation->finished_at = now();

            if ($payloadSnapshotPolicy === 'on_success') {
                $generation->payload_snapshot = $payload;
            }

            $generation->save();
        } catch (\Throwable $e) {
            $generation->status = 'failed';
            $generation->error_message = $e->getMessage();
            $generation->finished_at = now();

            if ($payloadSnapshotPolicy === 'on_success') {
                $generation->payload_snapshot = null;
            }

            $generation->save();

            throw $e;
        }
    }

    public function retryFailedGeneration(
        DocumentGeneration $generation,
    ): DocumentGeneration {
        if ($generation->status !== 'failed') {
            throw new RuntimeException(
                'Only failed generations can be retried.',
            );
        }

        $payload = $generation->payload_snapshot;
        if (!is_array($payload) || empty($payload)) {
            throw new RuntimeException(
                'Cannot retry: payload snapshot is missing.',
            );
        }

        $template = DocumentTemplate::query()->findOrFail(
            $generation->template_id,
        );

        if ($template->is_archived) {
            throw new RuntimeException('Template is archived.');
        }

        $version = DocumentTemplateVersion::query()->findOrFail(
            $generation->template_version_id,
        );

        /** @var array<string, mixed> $renderLog */
        $renderLog = is_array($generation->render_log)
            ? $generation->render_log
            : [];

        $renderLog['retry_of'] = (int) $generation->getKey();

        $mode = (string) ($generation->mode ?: 'final');

        $queueEnabled = (bool) config('docx-builder.queue.enabled', false);

        return $queueEnabled
            ? $this->queueWithVersion(
                $template,
                $version,
                $payload,
                $mode,
                $renderLog,
                sourceType: filled($generation->source_type)
                    ? (string) $generation->source_type
                    : null,
                sourceId: filled($generation->source_id)
                    ? (string) $generation->source_id
                    : null,
            )
            : $this->generateWithVersion(
                $template,
                $version,
                $payload,
                $mode,
                $renderLog,
                sourceType: filled($generation->source_type)
                    ? (string) $generation->source_type
                    : null,
                sourceId: filled($generation->source_id)
                    ? (string) $generation->source_id
                    : null,
            );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $renderLog
     */
    private function generateWithVersion(
        DocumentTemplate $template,
        DocumentTemplateVersion $version,
        array $payload,
        string $mode = 'final',
        array $renderLog = [],
        ?string $sourceType = null,
        ?string $sourceId = null,
    ): DocumentGeneration {
        [
            $outputDisk,
            $relativePath,
            $outputFilename,
        ] = $this->makeOutputPathAndName($template, $payload);

        $generation = new DocumentGeneration([
            'template_id' => $template->id,
            'template_version_id' => $version->id,
            'mode' => $mode,
            'status' => 'running',
            'output_disk' => $outputDisk,
            'output_path' => $relativePath,
            'output_filename' => $outputFilename,
            'user_id' => Auth::id(),
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'render_log' => $renderLog,
            'started_at' => now(),
        ]);

        $payloadSnapshotPolicy =
            (string) ($template->payload_snapshot_policy ?:
            config('docx-builder.payload_snapshot_policy', 'off'));

        if ($payloadSnapshotPolicy === 'always') {
            $generation->payload_snapshot = $payload;
        }

        $generation->save();

        try {
            $bytes = $this->renderer->render($version, $payload);
            Storage::disk($outputDisk)->put($relativePath, $bytes);

            $generation->status = 'success';
            $generation->finished_at = now();

            if ($payloadSnapshotPolicy === 'on_success') {
                $generation->payload_snapshot = $payload;
            }

            $generation->save();
        } catch (\Throwable $e) {
            $generation->status = 'failed';
            $generation->error_message = $e->getMessage();
            $generation->finished_at = now();

            if ($payloadSnapshotPolicy === 'on_success') {
                $generation->payload_snapshot = null;
            }

            $generation->save();

            throw $e;
        }

        return $generation->fresh();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $renderLog
     */
    private function queueWithVersion(
        DocumentTemplate $template,
        DocumentTemplateVersion $version,
        array $payload,
        string $mode = 'final',
        array $renderLog = [],
        ?string $sourceType = null,
        ?string $sourceId = null,
    ): DocumentGeneration {
        [
            $outputDisk,
            $relativePath,
            $outputFilename,
        ] = $this->makeOutputPathAndName($template, $payload);

        $generation = new DocumentGeneration([
            'template_id' => $template->id,
            'template_version_id' => $version->id,
            'mode' => $mode,
            'status' => 'queued',
            'output_disk' => $outputDisk,
            'output_path' => $relativePath,
            'output_filename' => $outputFilename,
            'user_id' => Auth::id(),
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'render_log' => $renderLog,
        ]);

        $payloadSnapshotPolicy =
            (string) ($template->payload_snapshot_policy ?:
            config('docx-builder.payload_snapshot_policy', 'off'));

        if ($payloadSnapshotPolicy === 'always') {
            $generation->payload_snapshot = $payload;
        }

        $generation->save();

        $dispatch = RenderDocumentGeneration::dispatch(
            $generation->id,
            $payload,
            $renderLog,
        );

        $connection = config('docx-builder.queue.connection');
        if (filled($connection)) {
            $dispatch->onConnection((string) $connection);
        }

        $queue = config('docx-builder.queue.queue');
        if (filled($queue)) {
            $dispatch->onQueue((string) $queue);
        }

        return $generation->fresh();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{0: string, 1: string, 2: string}
     */
    private function makeOutputPathAndName(
        DocumentTemplate $template,
        array $payload,
    ): array {
        $outputDisk = (string) config('docx-builder.output_disk', 'local');
        $prefix = (string) config(
            'docx-builder.output_path_prefix',
            'docx-builder',
        );

        $filenamePattern =
            $template->output_filename_pattern ?:
            'document_{doc.number}_{doc.date}';

        $outputFilename = FilenamePattern::make(
            $filenamePattern,
            $payload,
            $template->code ?: 'document',
        );

        $relativePath =
            trim($prefix, '/') .
            '/' .
            $template->id .
            '/' .
            date('Y/m') .
            '/' .
            uniqid('gen_', true) .
            '_' .
            $outputFilename;

        return [$outputDisk, $relativePath, $outputFilename];
    }
}
