<?php

use Arseno25\DocxBuilder\Filament\Pages\DocxBuilderSettings;
use Arseno25\DocxBuilder\Filament\Pages\GenerateDocument;
use Arseno25\DocxBuilder\Filament\Resources\DocumentGenerations\DocumentGenerationResource;
use Arseno25\DocxBuilder\Filament\Resources\DocumentGenerations\Pages\ListDocumentGenerations;
use Arseno25\DocxBuilder\Filament\Resources\DocumentGenerations\Pages\ViewDocumentGeneration;
use Arseno25\DocxBuilder\Filament\Resources\DocumentGenerations\Tables\DocumentGenerationsTable;
use Arseno25\DocxBuilder\Filament\Resources\DocumentTemplateCategories\DocumentTemplateCategoryResource;
use Arseno25\DocxBuilder\Filament\Resources\DocumentTemplateCategories\Pages\CreateDocumentTemplateCategory;
use Arseno25\DocxBuilder\Filament\Resources\DocumentTemplateCategories\Pages\EditDocumentTemplateCategory;
use Arseno25\DocxBuilder\Filament\Resources\DocumentTemplateCategories\Pages\ListDocumentTemplateCategories;
use Arseno25\DocxBuilder\Filament\Resources\DocumentTemplateCategories\Schemas\DocumentTemplateCategoryForm;
use Arseno25\DocxBuilder\Filament\Resources\DocumentTemplateCategories\Tables\DocumentTemplateCategoriesTable;
use Arseno25\DocxBuilder\Filament\Resources\DocumentTemplates\DocumentTemplateResource;
use Arseno25\DocxBuilder\Filament\Resources\DocumentTemplates\Pages\CreateDocumentTemplate;
use Arseno25\DocxBuilder\Filament\Resources\DocumentTemplates\Pages\EditDocumentTemplate;
use Arseno25\DocxBuilder\Filament\Resources\DocumentTemplates\Pages\ListDocumentTemplates;
use Arseno25\DocxBuilder\Filament\Resources\DocumentTemplates\RelationManagers\TemplateFieldsRelationManager;
use Arseno25\DocxBuilder\Filament\Resources\DocumentTemplates\RelationManagers\TemplatePresetsRelationManager;
use Arseno25\DocxBuilder\Filament\Resources\DocumentTemplates\RelationManagers\TemplateSequencesRelationManager;
use Arseno25\DocxBuilder\Filament\Resources\DocumentTemplates\RelationManagers\TemplateVersionsRelationManager;
use Arseno25\DocxBuilder\Filament\Resources\DocumentTemplates\Schemas\DocumentTemplateForm;
use Arseno25\DocxBuilder\Filament\Resources\DocumentTemplates\Tables\DocumentTemplatesTable;

it('autoloads all Filament v5 resources/pages (smoke)', function () {
    $classes = [
        DocxBuilderSettings::class,
        GenerateDocument::class,

        DocumentTemplateCategoryResource::class,
        ListDocumentTemplateCategories::class,
        CreateDocumentTemplateCategory::class,
        EditDocumentTemplateCategory::class,
        DocumentTemplateCategoryForm::class,
        DocumentTemplateCategoriesTable::class,

        DocumentTemplateResource::class,
        ListDocumentTemplates::class,
        CreateDocumentTemplate::class,
        EditDocumentTemplate::class,
        DocumentTemplateForm::class,
        DocumentTemplatesTable::class,
        TemplateVersionsRelationManager::class,
        TemplateFieldsRelationManager::class,
        TemplateSequencesRelationManager::class,
        TemplatePresetsRelationManager::class,

        DocumentGenerationResource::class,
        ListDocumentGenerations::class,
        ViewDocumentGeneration::class,
        DocumentGenerationsTable::class,
    ];

    foreach ($classes as $class) {
        expect(class_exists($class))->toBeTrue();
    }
});
