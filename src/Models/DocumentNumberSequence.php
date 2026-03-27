<?php

namespace Arseno25\DocxBuilder\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentNumberSequence extends Model
{
    protected $table = 'docx_number_sequences';

    protected $fillable = [
        'template_id',
        'key',
        'pattern',
        'counter',
        'reset_policy',
        'last_reset_at',
        'is_active',
    ];

    protected $casts = [
        'template_id' => 'int',
        'counter' => 'int',
        'last_reset_at' => 'datetime',
        'is_active' => 'bool',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(DocumentTemplate::class, 'template_id');
    }
}
