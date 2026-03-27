<?php

namespace Arseno25\DocxBuilder\Services;

use Arseno25\DocxBuilder\Models\DocxSetting;
use Illuminate\Support\Facades\Schema;

class DocxSettingsService
{
    public function tableExists(): bool
    {
        try {
            return Schema::hasTable('docx_settings');
        } catch (\Throwable) {
            return false;
        }
    }

    public function get(): ?DocxSetting
    {
        if (!$this->tableExists()) {
            return null;
        }

        return DocxSetting::query()->orderBy('id')->first();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function save(array $data, ?int $userId = null): DocxSetting
    {
        $setting = $this->get() ?? new DocxSetting();

        if (!$setting->exists) {
            $setting->created_by = $userId;
        }
        $setting->updated_by = $userId;

        $setting->fill($data);
        $setting->save();

        return $setting->fresh();
    }

    public function applyToConfig(?DocxSetting $setting): void
    {
        if (!$setting) {
            return;
        }

        if (filled($setting->template_disk)) {
            config()->set(
                'docx-builder.template_disk',
                $setting->template_disk,
            );
        }

        if (filled($setting->output_disk)) {
            config()->set('docx-builder.output_disk', $setting->output_disk);
        }

        if (filled($setting->output_path_prefix)) {
            config()->set(
                'docx-builder.output_path_prefix',
                $setting->output_path_prefix,
            );
        }

        $retention = (array) config('docx-builder.retention_days', []);
        if ($setting->retention_test_days !== null) {
            $retention['test'] = (int) $setting->retention_test_days;
        }
        $retention['final'] =
            $setting->retention_final_days !== null
                ? (int) $setting->retention_final_days
                : null;
        config()->set('docx-builder.retention_days', $retention);

        if (filled($setting->payload_snapshot_policy)) {
            config()->set(
                'docx-builder.payload_snapshot_policy',
                $setting->payload_snapshot_policy,
            );
        }

        config()->set(
            'docx-builder.queue.enabled',
            (bool) $setting->queue_enabled,
        );
        config()->set(
            'docx-builder.queue.connection',
            $setting->queue_connection,
        );
        config()->set('docx-builder.queue.queue', $setting->queue_queue);

        config()->set(
            'docx-builder.preview.enabled_by_default',
            (bool) $setting->preview_enabled_by_default,
        );
        config()->set(
            'docx-builder.preview.max_chars',
            (int) ($setting->preview_max_chars ?: 12000),
        );
        config()->set(
            'docx-builder.preview.debounce_ms',
            (int) ($setting->preview_debounce_ms ?: 700),
        );

        config()->set(
            'docx-builder.preview.layout.enabled',
            (bool) $setting->layout_preview_enabled,
        );
        config()->set(
            'docx-builder.preview.layout.enabled_by_default',
            (bool) $setting->layout_preview_enabled_by_default,
        );

        if (filled($setting->layout_preview_driver)) {
            config()->set(
                'docx-builder.preview.layout.driver',
                (string) $setting->layout_preview_driver,
            );
        }

        if (filled($setting->layout_preview_soffice_binary)) {
            config()->set(
                'docx-builder.preview.layout.soffice_binary',
                (string) $setting->layout_preview_soffice_binary,
            );
        }

        if (filled($setting->layout_preview_disk)) {
            config()->set(
                'docx-builder.preview.layout.disk',
                (string) $setting->layout_preview_disk,
            );
        }

        if (filled($setting->layout_preview_path_prefix)) {
            config()->set(
                'docx-builder.preview.layout.path_prefix',
                (string) $setting->layout_preview_path_prefix,
            );
        }

        config()->set(
            'docx-builder.preview.layout.ttl_minutes',
            (int) ($setting->layout_preview_ttl_minutes ?: 10),
        );
    }
}
