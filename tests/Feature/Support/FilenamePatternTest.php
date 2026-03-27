<?php

use Arseno25\DocxBuilder\Support\FilenamePattern;

it('renders filename pattern and appends docx', function () {
    $name = FilenamePattern::make('INV_{doc.number}_{doc.date}', [
        'doc' => [
            'number' => 'INV-2026-0001',
            'date' => '2026-03-27',
        ],
    ]);

    expect($name)->toBe('INV_INV-2026-0001_2026-03-27.docx');
});

it('sanitizes dangerous characters and path traversal', function () {
    $name = FilenamePattern::make('../evil\\name:*?"<>|', []);

    expect($name)->toEndWith('.docx');
    expect($name)->not->toContain('..');
    expect($name)->not->toContain('/');
    expect($name)->not->toContain('\\');
});

it('falls back when pattern compiles to empty', function () {
    $name = FilenamePattern::make('{doc.missing}', ['doc' => []], 'fallback');

    expect($name)->toBe('fallback.docx');
});
