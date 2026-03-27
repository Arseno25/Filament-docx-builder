<?php

namespace Arseno25\DocxBuilder\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentTemplateCategory extends Model
{
    protected $table = 'docx_template_categories';

    protected $fillable = [
        'code',
        'name',
        'description',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'bool',
        'sort_order' => 'int',
    ];

    public function templates(): HasMany
    {
        return $this->hasMany(DocumentTemplate::class, 'category_id');
    }
}
