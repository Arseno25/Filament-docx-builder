<?php

namespace Arseno25\DocxBuilder\Models;

use Illuminate\Database\Eloquent\Model;

class DocxSetting extends Model
{
    protected $table = 'docx_settings';

    protected $fillable = [
        'template_disk',
        'output_disk',
        'output_path_prefix',
        'retention_test_days',
        'retention_final_days',
        'payload_snapshot_policy',
        'queue_enabled',
        'queue_connection',
        'queue_queue',
        'preview_enabled_by_default',
        'preview_max_chars',
        'preview_debounce_ms',
        'layout_preview_enabled',
        'layout_preview_enabled_by_default',
        'layout_preview_driver',
        'layout_preview_soffice_binary',
        'layout_preview_disk',
        'layout_preview_path_prefix',
        'layout_preview_ttl_minutes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'retention_test_days' => 'int',
        'retention_final_days' => 'int',
        'queue_enabled' => 'bool',
        'preview_enabled_by_default' => 'bool',
        'preview_max_chars' => 'int',
        'preview_debounce_ms' => 'int',
        'layout_preview_enabled' => 'bool',
        'layout_preview_enabled_by_default' => 'bool',
        'layout_preview_ttl_minutes' => 'int',
        'created_by' => 'int',
        'updated_by' => 'int',
    ];
}
