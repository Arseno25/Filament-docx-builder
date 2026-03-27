<?php

use Arseno25\DocxBuilder\Filament\Resources\DocumentTemplates\Pages\CreateDocumentTemplate;
use Arseno25\DocxBuilder\Filament\Resources\DocumentTemplates\Pages\ListDocumentTemplates;
use Arseno25\DocxBuilder\Models\DocumentTemplate;
use Arseno25\DocxBuilder\Support\DocxBuilderPermissions;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

it(
    'creates a template via Filament resource page (PRD 15.1 template management)',
    function () {
        loginWithPermissions([
            DocxBuilderPermissions::TEMPLATES_VIEW_ANY,
            DocxBuilderPermissions::TEMPLATES_CREATE,
        ]);

        Livewire::test(CreateDocumentTemplate::class)
            ->fillForm([
                'code' => 'TMP-R1',
                'name' => 'Template Resource',
                'status' => 'draft',
                'visibility' => 'internal',
                'payload_snapshot_policy' => 'off',
            ])
            ->call('create');

        $template = DocumentTemplate::query()
            ->where('code', 'TMP-R1')
            ->firstOrFail();

        expect($template->name)->toBe('Template Resource');
        expect($template->status)->toBe('draft');
    },
);

it(
    'duplicates a template from the list table action (PRD 15.1 template management)',
    function () {
        loginWithPermissions([DocxBuilderPermissions::TEMPLATES_VIEW_ANY]);

        Carbon::setTestNow(Carbon::create(2026, 3, 27, 9, 0, 1));

        $template = DocumentTemplate::create([
            'code' => 'TMP-R2',
            'name' => 'Template',
            'status' => 'active',
            'visibility' => 'internal',
            'is_archived' => true,
            'active_version_id' => 123,
            'payload_snapshot_policy' => 'off',
        ]);

        Livewire::test(ListDocumentTemplates::class)->callTableAction(
            'duplicate',
            $template->getKey(),
        );

        $copy = DocumentTemplate::query()
            ->where('id', '!=', $template->id)
            ->latest('id')
            ->firstOrFail();

        expect($copy->code)->toBe('TMP-R2_copy_20260327090001');
        expect($copy->status)->toBe('draft');
        expect($copy->active_version_id)->toBeNull();
        expect($copy->is_archived)->toBeFalse();
    },
);

it(
    'archives and unarchives a template from the list table action (PRD 15.1 template management)',
    function () {
        loginWithPermissions([DocxBuilderPermissions::TEMPLATES_VIEW_ANY]);

        $template = DocumentTemplate::create([
            'code' => 'TMP-R3',
            'name' => 'Template',
            'status' => 'draft',
            'visibility' => 'internal',
            'is_archived' => false,
            'payload_snapshot_policy' => 'off',
        ]);

        Livewire::test(ListDocumentTemplates::class)->callTableAction(
            'archive',
            $template->getKey(),
        );

        $template->refresh();
        expect($template->is_archived)->toBeTrue();

        Livewire::test(ListDocumentTemplates::class)->callTableAction(
            'archive',
            $template->getKey(),
        );

        $template->refresh();
        expect($template->is_archived)->toBeFalse();
    },
);
