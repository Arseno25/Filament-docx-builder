<?php

use Arseno25\DocxBuilder\Filament\Pages\DocxBuilderSettings;
use Arseno25\DocxBuilder\Models\DocxSetting;
use Arseno25\DocxBuilder\Support\DocxBuilderPermissions;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    Carbon::setTestNow(Carbon::create(2026, 3, 27, 10, 0, 0));

    loginWithPermissions([DocxBuilderPermissions::SETTINGS_VIEW]);
});

afterEach(function () {
    Carbon::setTestNow();
});

it(
    'persists settings to the database and applies them to runtime config',
    function () {
        expect(DocxSetting::query()->count())->toBe(0);

        Livewire::test(DocxBuilderSettings::class)
            ->set('data.template_disk', 'local')
            ->set('data.output_disk', 'local')
            ->set('data.output_path_prefix', 'docx-builder-test')
            ->set('data.retention_test_days', 3)
            ->set('data.retention_final_days', null)
            ->set('data.payload_snapshot_policy', 'always')
            ->set('data.queue_enabled', true)
            ->set('data.queue_connection', 'database')
            ->set('data.queue_queue', 'docx')
            ->set('data.preview_enabled_by_default', true)
            ->set('data.preview_max_chars', 9000)
            ->set('data.preview_debounce_ms', 500)
            ->set('data.layout_preview_enabled', true)
            ->set('data.layout_preview_enabled_by_default', true)
            ->set('data.layout_preview_driver', 'libreoffice')
            ->set('data.layout_preview_soffice_binary', 'soffice')
            ->set('data.layout_preview_disk', '')
            ->set('data.layout_preview_path_prefix', 'docx-builder/previews')
            ->set('data.layout_preview_ttl_minutes', 5)
            ->call('save');

        expect(DocxSetting::query()->count())->toBe(1);

        $setting = DocxSetting::query()->firstOrFail();
        expect($setting->output_path_prefix)->toBe('docx-builder-test');
        expect((int) $setting->retention_test_days)->toBe(3);
        expect($setting->retention_final_days)->toBeNull();
        expect($setting->queue_enabled)->toBeTrue();

        expect(config('docx-builder.output_path_prefix'))->toBe(
            'docx-builder-test',
        );
        expect(config('docx-builder.retention_days.test'))->toBe(3);
        expect(config('docx-builder.retention_days.final'))->toBeNull();
        expect(config('docx-builder.payload_snapshot_policy'))->toBe('always');
        expect(config('docx-builder.queue.enabled'))->toBeTrue();
        expect(config('docx-builder.queue.connection'))->toBe('database');
        expect(config('docx-builder.queue.queue'))->toBe('docx');
        expect(config('docx-builder.preview.max_chars'))->toBe(9000);
        expect(config('docx-builder.preview.debounce_ms'))->toBe(500);
        expect(config('docx-builder.preview.layout.enabled'))->toBeTrue();
        expect(
            config('docx-builder.preview.layout.enabled_by_default'),
        )->toBeTrue();
        expect(config('docx-builder.preview.layout.driver'))->toBe(
            'libreoffice',
        );
        expect(config('docx-builder.preview.layout.soffice_binary'))->toBe(
            'soffice',
        );
        expect(config('docx-builder.preview.layout.path_prefix'))->toBe(
            'docx-builder/previews',
        );
        expect(config('docx-builder.preview.layout.ttl_minutes'))->toBe(5);
    },
);
