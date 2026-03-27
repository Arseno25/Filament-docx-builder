<?php

namespace Arseno25\DocxBuilder\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentTemplateField extends Model
{
    protected $table = 'docx_template_fields';

    protected $fillable = [
        'template_id',
        'group_name',
        'sort_order',
        'label',
        'key',
        'type',
        'placeholder_tag',
        'required',
        'default_value',
        'help_text',
        'validation_rules',
        'visibility_rules',
        'transform_rules',
        'data_source_type',
        'data_source_config',
    ];

    protected $casts = [
        'template_id' => 'int',
        'sort_order' => 'int',
        'required' => 'bool',
        'default_value' => 'array',
        'validation_rules' => 'array',
        'visibility_rules' => 'array',
        'transform_rules' => 'array',
        'data_source_config' => 'array',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(DocumentTemplate::class, 'template_id');
    }
}
