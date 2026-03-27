<?php

namespace Arseno25\DocxBuilder\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentTemplateVersion extends Model
{
    protected $table = 'docx_template_versions';

    protected $fillable = [
        'template_id',
        'version',
        'is_active',
        'changelog',
        'source_disk',
        'source_path',
        'original_filename',
        'checksum',
        'schema_snapshot',
        'uploaded_by',
    ];

    protected $casts = [
        'template_id' => 'int',
        'is_active' => 'bool',
        'schema_snapshot' => 'array',
        'uploaded_by' => 'int',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(DocumentTemplate::class, 'template_id');
    }

    public function generations(): HasMany
    {
        return $this->hasMany(DocumentGeneration::class, 'template_version_id');
    }
}
