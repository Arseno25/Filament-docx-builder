<?php

namespace Arseno25\DocxBuilder\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentPreset extends Model
{
    protected $table = 'docx_presets';

    protected $fillable = [
        'template_id',
        'key',
        'label',
        'type',
        'value',
        'disk',
        'path',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'template_id' => 'int',
        'value' => 'array',
        'sort_order' => 'int',
        'is_active' => 'bool',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(DocumentTemplate::class, 'template_id');
    }
}
