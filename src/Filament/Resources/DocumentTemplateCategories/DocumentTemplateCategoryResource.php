<?php

namespace Arseno25\DocxBuilder\Filament\Resources\DocumentTemplateCategories;

use Arseno25\DocxBuilder\Filament\Resources\DocumentTemplateCategories\Pages;
use Arseno25\DocxBuilder\Filament\Resources\DocumentTemplateCategories\Schemas\DocumentTemplateCategoryForm;
use Arseno25\DocxBuilder\Filament\Resources\DocumentTemplateCategories\Tables\DocumentTemplateCategoriesTable;
use Arseno25\DocxBuilder\Models\DocumentTemplateCategory;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use UnitEnum;

class DocumentTemplateCategoryResource extends Resource
{
    protected static ?string $model = DocumentTemplateCategory::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-tag';

    protected static string | UnitEnum | null $navigationGroup = 'Documents';

    public static function form(Schema $schema): Schema
    {
        return DocumentTemplateCategoryForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DocumentTemplateCategoriesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDocumentTemplateCategories::route('/'),
            'create' => Pages\CreateDocumentTemplateCategory::route('/create'),
            'edit' => Pages\EditDocumentTemplateCategory::route('/{record}/edit'),
        ];
    }
}
