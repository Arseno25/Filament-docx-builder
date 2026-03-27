<?php

namespace Arseno25\DocxBuilder\Filament\Resources\DocumentTemplates;

use Arseno25\DocxBuilder\Filament\Resources\DocumentTemplates\Pages;
use Arseno25\DocxBuilder\Filament\Resources\DocumentTemplates\RelationManagers\TemplateFieldsRelationManager;
use Arseno25\DocxBuilder\Filament\Resources\DocumentTemplates\RelationManagers\TemplatePresetsRelationManager;
use Arseno25\DocxBuilder\Filament\Resources\DocumentTemplates\RelationManagers\TemplateSequencesRelationManager;
use Arseno25\DocxBuilder\Filament\Resources\DocumentTemplates\RelationManagers\TemplateVersionsRelationManager;
use Arseno25\DocxBuilder\Filament\Resources\DocumentTemplates\Schemas\DocumentTemplateForm;
use Arseno25\DocxBuilder\Filament\Resources\DocumentTemplates\Tables\DocumentTemplatesTable;
use Arseno25\DocxBuilder\Models\DocumentTemplate;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use UnitEnum;

class DocumentTemplateResource extends Resource
{
    protected static ?string $model = DocumentTemplate::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-document-text';

    protected static string|UnitEnum|null $navigationGroup = 'Documents';

    public static function form(Schema $schema): Schema
    {
        return DocumentTemplateForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DocumentTemplatesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            TemplateVersionsRelationManager::class,
            TemplateFieldsRelationManager::class,
            TemplateSequencesRelationManager::class,
            TemplatePresetsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDocumentTemplates::route('/'),
            'create' => Pages\CreateDocumentTemplate::route('/create'),
            'edit' => Pages\EditDocumentTemplate::route('/{record}/edit'),
        ];
    }
}
