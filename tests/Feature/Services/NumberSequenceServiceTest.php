<?php

use Arseno25\DocxBuilder\Models\DocumentNumberSequence;
use Arseno25\DocxBuilder\Models\DocumentTemplate;
use Arseno25\DocxBuilder\Services\NumberSequenceService;

it('formats sequence with padding and roman month', function () {
    $template = DocumentTemplate::create([
        'code' => 'TMP-S',
        'name' => 'Template S',
        'status' => 'draft',
        'visibility' => 'internal',
        'payload_snapshot_policy' => 'off',
    ]);

    $seq = DocumentNumberSequence::create([
        'template_id' => $template->id,
        'key' => 'number',
        'pattern' => '{seq:3}/SKD/{roman_month}/{year}',
        'counter' => 0,
        'reset_policy' => 'never',
        'is_active' => true,
    ]);

    $out = app(NumberSequenceService::class)->nextNumber(
        $seq,
        new DateTimeImmutable('2026-03-27'),
    );

    expect($out)->toBe('001/SKD/III/2026');
});

it('can peek the next number without incrementing the counter', function () {
    $template = DocumentTemplate::create([
        'code' => 'TMP-S-PEEK',
        'name' => 'Template',
        'status' => 'draft',
        'visibility' => 'internal',
        'payload_snapshot_policy' => 'off',
    ]);

    $seq = DocumentNumberSequence::create([
        'template_id' => $template->id,
        'key' => 'number',
        'pattern' => '{seq:3}/SKD/{roman_month}/{year}',
        'counter' => 7,
        'reset_policy' => 'never',
        'is_active' => true,
    ]);

    $out = app(NumberSequenceService::class)->peekNextNumber(
        $seq,
        new DateTimeImmutable('2026-03-27'),
    );

    $seq->refresh();

    expect($out)->toBe('008/SKD/III/2026');
    expect($seq->counter)->toBe(7);
});

it('resets yearly counters', function () {
    $template = DocumentTemplate::create([
        'code' => 'TMP-S2',
        'name' => 'Template S2',
        'status' => 'draft',
        'visibility' => 'internal',
        'payload_snapshot_policy' => 'off',
    ]);

    $seq = DocumentNumberSequence::create([
        'template_id' => $template->id,
        'key' => 'number',
        'pattern' => '{seq:2}/{year}',
        'counter' => 9,
        'reset_policy' => 'yearly',
        'last_reset_at' => '2025-12-31 00:00:00',
        'is_active' => true,
    ]);

    $out = app(NumberSequenceService::class)->nextNumber(
        $seq,
        new DateTimeImmutable('2026-01-01'),
    );

    expect($out)->toBe('01/2026');
});
