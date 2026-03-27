<?php

namespace Arseno25\DocxBuilder\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentGeneration extends Model
{
    protected $table = 'docx_generations';

    protected $fillable = [
        'template_id',
        'template_version_id',
        'mode',
        'status',
        'output_disk',
        'output_path',
        'output_filename',
        'mime_type',
        'size_bytes',
        'checksum',
        'user_id',
        'source_type',
        'source_id',
        'payload_snapshot',
        'error_message',
        'render_log',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'template_id' => 'int',
        'template_version_id' => 'int',
        'size_bytes' => 'int',
        'user_id' => 'int',
        'payload_snapshot' => 'array',
        'render_log' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(DocumentTemplate::class, 'template_id');
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(DocumentTemplateVersion::class, 'template_version_id');
    }
}
