<?php

namespace Arseno25\DocxBuilder\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class DocxBuilderCommand extends Command
{
    public $signature = 'docx-builder {--json : Output machine-readable JSON}';

    public $description = 'Inspect the Docx Builder installation and configuration.';

    public function handle(): int
    {
        $templateDisk = (string) config('docx-builder.template_disk', 'local');
        $outputDisk = (string) config('docx-builder.output_disk', 'local');

        $report = [
            'config' => [
                'template_disk' => $templateDisk,
                'output_disk' => $outputDisk,
                'output_path_prefix' => (string) config(
                    'docx-builder.output_path_prefix',
                    'docx-builder',
                ),
                'payload_snapshot_policy' => (string) config(
                    'docx-builder.payload_snapshot_policy',
                    'off',
                ),
                'queue' => [
                    'enabled' => (bool) config(
                        'docx-builder.queue.enabled',
                        false,
                    ),
                    'connection' => config('docx-builder.queue.connection'),
                    'queue' => config('docx-builder.queue.queue'),
                ],
                'preview' => [
                    'enabled_by_default' => (bool) config(
                        'docx-builder.preview.enabled_by_default',
                        true,
                    ),
                    'max_chars' => (int) config(
                        'docx-builder.preview.max_chars',
                        12000,
                    ),
                    'debounce_ms' => (int) config(
                        'docx-builder.preview.debounce_ms',
                        700,
                    ),
                ],
            ],
            'storage' => [
                'template_disk_exists' => $this->diskIsConfigured(
                    $templateDisk,
                ),
                'output_disk_exists' => $this->diskIsConfigured($outputDisk),
            ],
            'database' => [
                'tables' => [],
                'counts' => [],
            ],
        ];

        $tables = [
            'docx_template_categories',
            'docx_templates',
            'docx_template_versions',
            'docx_template_fields',
            'docx_presets',
            'docx_number_sequences',
            'docx_generations',
            'docx_settings',
        ];

        foreach ($tables as $table) {
            $report['database']['tables'][$table] = Schema::hasTable($table);
        }

        if ($report['database']['tables']['docx_templates'] ?? false) {
            $report['database']['counts'] = [
                'templates' => (int) \DB::table('docx_templates')->count(),
                'versions' => Schema::hasTable('docx_template_versions')
                    ? (int) \DB::table('docx_template_versions')->count()
                    : null,
                'fields' => Schema::hasTable('docx_template_fields')
                    ? (int) \DB::table('docx_template_fields')->count()
                    : null,
                'presets' => Schema::hasTable('docx_presets')
                    ? (int) \DB::table('docx_presets')->count()
                    : null,
                'sequences' => Schema::hasTable('docx_number_sequences')
                    ? (int) \DB::table('docx_number_sequences')->count()
                    : null,
                'generations' => Schema::hasTable('docx_generations')
                    ? (int) \DB::table('docx_generations')->count()
                    : null,
            ];
        }

        if ((bool) $this->option('json')) {
            $this->line(
                json_encode(
                    $report,
                    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES,
                ) ?:
                '{}',
            );

            return self::SUCCESS;
        }

        $this->info('Docx Builder report');
        $this->line('');

        $this->line('Configuration:');
        $this->line("- Template disk: {$templateDisk}");
        $this->line("- Output disk: {$outputDisk}");
        $this->line(
            '- Output path prefix: ' .
                (string) $report['config']['output_path_prefix'],
        );
        $this->line(
            '- Queue enabled: ' .
                (((bool) $report['config']['queue']['enabled']) ? 'yes' : 'no'),
        );
        $this->line(
            '- Live preview enabled by default: ' .
                (((bool) $report['config']['preview']['enabled_by_default'])
                    ? 'yes'
                    : 'no'),
        );

        $this->line('');
        $this->line('Storage:');
        $this->line(
            '- Template disk configured: ' .
                ($report['storage']['template_disk_exists'] ?? false
                    ? 'yes'
                    : 'no'),
        );
        $this->line(
            '- Output disk configured: ' .
                ($report['storage']['output_disk_exists'] ?? false
                    ? 'yes'
                    : 'no'),
        );

        $this->line('');
        $this->line('Database:');

        foreach ($report['database']['tables'] as $table => $exists) {
            $this->line("- {$table}: " . ($exists ? 'present' : 'missing'));
        }

        if (!empty($report['database']['counts'])) {
            $this->line('');
            $this->line('Counts:');
            foreach ($report['database']['counts'] as $key => $count) {
                if ($count === null) {
                    continue;
                }

                $this->line("- {$key}: {$count}");
            }
        }

        return self::SUCCESS;
    }

    private function diskIsConfigured(string $disk): bool
    {
        try {
            Storage::disk($disk);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
