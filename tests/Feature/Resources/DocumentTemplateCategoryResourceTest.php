<?php

use Arseno25\DocxBuilder\Filament\Resources\DocumentTemplateCategories\Pages\CreateDocumentTemplateCategory;
use Arseno25\DocxBuilder\Models\DocumentTemplateCategory;
use Arseno25\DocxBuilder\Support\DocxBuilderPermissions;
use Livewire\Livewire;

it(
    'creates a template category via Filament resource page (PRD 15.1 template categories)',
    function () {
        loginWithPermissions([
            DocxBuilderPermissions::CATEGORIES_VIEW_ANY,
            DocxBuilderPermissions::CATEGORIES_CREATE,
        ]);

        Livewire::test(CreateDocumentTemplateCategory::class)
            ->fillForm([
                'code' => 'CAT-R1',
                'name' => 'Category Resource',
                'sort_order' => 10,
                'is_active' => true,
            ])
            ->call('create');

        $category = DocumentTemplateCategory::query()
            ->where('code', 'CAT-R1')
            ->firstOrFail();

        expect($category->name)->toBe('Category Resource');
        expect($category->sort_order)->toBe(10);
    },
);
