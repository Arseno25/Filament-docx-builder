<?php

namespace Arseno25\DocxBuilder\Http\Controllers;

use Arseno25\DocxBuilder\Models\DocumentGeneration;
use Arseno25\DocxBuilder\Models\DocumentTemplate;
use Arseno25\DocxBuilder\Services\GenerationPayloadBuilder;
use Arseno25\DocxBuilder\Services\GenerationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class DocxBuilderApiController
{
    public function generate(Request $request): JsonResponse
    {
        /** @var array<string, mixed> $data */
        $data = $request->validate([
            'template_id' => ['required', 'integer', 'min:1'],
            'mode' => ['nullable', 'in:test,final'],
            'fields' => ['nullable', 'array'],
            'apply_presets' => ['nullable', 'boolean'],
            'source_record_id' => ['nullable'],
            'strict_source' => ['nullable', 'boolean'],
            'use_dummy_data' => ['nullable', 'boolean'],
            'use_numbering' => ['nullable', 'boolean'],
            'sequence_id' => ['nullable', 'integer', 'min:1'],
            'queue' => ['nullable', 'boolean'],
        ]);

        $template = DocumentTemplate::query()->findOrFail((int) $data['template_id']);
        $mode = (string) ($data['mode'] ?? 'final');

        $inputFields = $data['fields'] ?? [];
        $inputFields = is_array($inputFields) ? $inputFields : [];

        $renderLog = [
            'warnings' => [],
            'numbering' => null,
        ];

        /** @var GenerationPayloadBuilder $builder */
        $builder = app(GenerationPayloadBuilder::class);

        try {
            $built = $builder->build(
                $template,
                $inputFields,
                [
                    'apply_presets' => (bool) ($data['apply_presets'] ?? true),
                    'source_record_id' => $data['source_record_id'] ?? null,
                    'strict_source' => (bool) ($data['strict_source'] ?? false),
                    'use_dummy_data' => (bool) ($data['use_dummy_data'] ?? false),
                    'use_numbering' => (bool) ($data['use_numbering'] ?? ($mode === 'final')),
                    'sequence_id' => $data['sequence_id'] ?? null,
                ],
                $mode,
                $renderLog,
                preview: false,
            );
        } catch (\Throwable $e) {
            return response()->json(
                [
                    'message' => 'Unable to build generation payload.',
                    'error' => $e->getMessage(),
                ],
                422,
            );
        }

        $payload = $built['payload'];
        $warnings = $built['warnings'];
        $renderLog['warnings'] = $warnings;

        $missing = $builder->validateRequiredFields($template, $built['fields']);
        if (!empty($missing)) {
            return response()->json(
                [
                    'message' => 'Some required fields are missing.',
                    'missing' => $missing,
                ],
                422,
            );
        }

        $queueEnabled = (bool) config('docx-builder.queue.enabled', false);
        $queueRequested = (bool) ($data['queue'] ?? $queueEnabled);
        if ($queueRequested && !$queueEnabled) {
            return response()->json(
                [
                    'message' => 'Queue is not enabled on this installation.',
                ],
                422,
            );
        }

        /** @var GenerationService $service */
        $service = app(GenerationService::class);

        try {
            $generation = $queueRequested
                ? $service->queue(
                    $template,
                    $payload,
                    $mode,
                    $renderLog,
                    sourceType: filled($template->source_model_class)
                        ? (string) $template->source_model_class
                        : null,
                    sourceId: filled($data['source_record_id'] ?? null)
                        ? (string) $data['source_record_id']
                        : null,
                )
                : $service->generate(
                    $template,
                    $payload,
                    $mode,
                    $renderLog,
                    sourceType: filled($template->source_model_class)
                        ? (string) $template->source_model_class
                        : null,
                    sourceId: filled($data['source_record_id'] ?? null)
                        ? (string) $data['source_record_id']
                        : null,
                );
        } catch (RuntimeException $e) {
            return response()->json(
                [
                    'message' => $e->getMessage(),
                ],
                422,
            );
        } catch (\Throwable $e) {
            return response()->json(
                [
                    'message' => 'Failed to generate document.',
                    'error' => $e->getMessage(),
                ],
                500,
            );
        }

        return response()->json(
            $this->serializeGeneration($generation),
            $generation->status === 'queued' ? 202 : 201,
        );
    }

    public function show(DocumentGeneration $generation): JsonResponse
    {
        return response()->json($this->serializeGeneration($generation));
    }

    public function download(DocumentGeneration $generation)
    {
        if ($generation->status !== 'success') {
            return response()->json(
                ['message' => 'Document is not available for download.'],
                409,
            );
        }

        return Storage::disk($generation->output_disk)->download(
            $generation->output_path,
            $generation->output_filename,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeGeneration(DocumentGeneration $generation): array
    {
        $showUrl = route('docx-builder.api.generations.show', [
            'generation' => $generation,
        ]);

        $downloadUrl = $generation->status === 'success'
            ? route('docx-builder.api.generations.download', [
                'generation' => $generation,
            ])
            : null;

        return [
            'id' => $generation->getKey(),
            'template_id' => $generation->template_id,
            'template_version_id' => $generation->template_version_id,
            'mode' => $generation->mode,
            'status' => $generation->status,
            'output_disk' => $generation->output_disk,
            'output_path' => $generation->output_path,
            'output_filename' => $generation->output_filename,
            'error_message' => $generation->error_message,
            'render_log' => $generation->render_log,
            'created_at' => optional($generation->created_at)?->toIso8601String(),
            'started_at' => optional($generation->started_at)?->toIso8601String(),
            'finished_at' => optional($generation->finished_at)?->toIso8601String(),
            'links' => [
                'self' => $showUrl,
                'download' => $downloadUrl,
            ],
        ];
    }
}
