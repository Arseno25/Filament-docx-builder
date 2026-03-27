<?php

namespace Arseno25\DocxBuilder\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentTemplate extends Model
{
    protected $table = 'docx_templates';

    protected $fillable = [
        'category_id',
        'code',
        'name',
        'description',
        'status',
        'visibility',
        'is_archived',
        'output_filename_pattern',
        'payload_snapshot_policy',
        'active_version_id',
        'source_model_class',
        'source_model_label_attribute',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'category_id' => 'int',
        'is_archived' => 'bool',
        'created_by' => 'int',
        'updated_by' => 'int',
        'active_version_id' => 'int',
        'source_model_class' => 'string',
        'source_model_label_attribute' => 'string',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(DocumentTemplateCategory::class, 'category_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(DocumentTemplateVersion::class, 'template_id');
    }

    public function activeVersion(): BelongsTo
    {
        return $this->belongsTo(
            DocumentTemplateVersion::class,
            'active_version_id',
        );
    }

    public function fields(): HasMany
    {
        return $this->hasMany(DocumentTemplateField::class, 'template_id');
    }

    public function presets(): HasMany
    {
        return $this->hasMany(DocumentPreset::class, 'template_id');
    }

    public function sequences(): HasMany
    {
        return $this->hasMany(DocumentNumberSequence::class, 'template_id');
    }

    public function generations(): HasMany
    {
        return $this->hasMany(DocumentGeneration::class, 'template_id');
    }
}
