<?php

namespace Arseno25\DocxBuilder\Jobs;

use Arseno25\DocxBuilder\Services\GenerationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RenderDocumentGeneration implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $renderLog
     */
    public function __construct(
        public readonly int $generationId,
        public readonly array $payload,
        public readonly array $renderLog = [],
    ) {}

    public int $tries = 3;

    public function backoff(): array
    {
        return [5, 15, 30];
    }

    public function handle(GenerationService $service): void
    {
        $service->renderQueuedGeneration(
            $this->generationId,
            $this->payload,
            $this->renderLog,
        );
    }
}
