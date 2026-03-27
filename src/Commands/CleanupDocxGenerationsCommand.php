<?php

namespace Arseno25\DocxBuilder\Commands;

use Arseno25\DocxBuilder\Models\DocumentGeneration;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

class CleanupDocxGenerationsCommand extends Command
{
    protected $signature = 'docx-builder:cleanup {--mode=all : test|final|all} {--dry-run : Do not delete anything}';

    protected $description = 'Delete old generated documents based on the retention policy.';

    public function handle(): int
    {
        $mode = (string) $this->option('mode');
        $dryRun = (bool) $this->option('dry-run');

        $modes = match ($mode) {
            'test' => ['test'],
            'final' => ['final'],
            default => ['test', 'final'],
        };

        $deleted = 0;

        foreach ($modes as $singleMode) {
            $days = config("docx-builder.retention_days.{$singleMode}");
            if ($days === null) {
                continue;
            }

            $cutoff = Carbon::now()->subDays((int) $days);

            $query = DocumentGeneration::query()
                ->where('mode', $singleMode)
                ->whereNotNull('finished_at')
                ->where('finished_at', '<', $cutoff)
                ->orderBy('id');

            /** @var \Illuminate\Support\Collection<int, DocumentGeneration> $rows */
            $rows = $query->get();

            foreach ($rows as $row) {
                if (! $dryRun) {
                    if (filled($row->output_disk) && filled($row->output_path)) {
                        Storage::disk($row->output_disk)->delete($row->output_path);
                    }

                    $row->delete();
                }

                $deleted++;
            }
        }

        $this->info($dryRun
            ? "Dry-run: would delete {$deleted} generation(s)."
            : "Deleted {$deleted} generation(s).");

        return self::SUCCESS;
    }
}
