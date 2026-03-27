<?php

namespace Arseno25\DocxBuilder\Filament\Resources\DocumentGenerations;

use Arseno25\DocxBuilder\Filament\Resources\DocumentGenerations\Pages;
use Arseno25\DocxBuilder\Filament\Resources\DocumentGenerations\Tables\DocumentGenerationsTable;
use Arseno25\DocxBuilder\Models\DocumentGeneration;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use UnitEnum;

class DocumentGenerationResource extends Resource
{
    protected static ?string $model = DocumentGeneration::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-clock';

    protected static string | UnitEnum | null $navigationGroup = 'Documents';

    public static function table(Table $table): Table
    {
        return DocumentGenerationsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDocumentGenerations::route('/'),
            'view' => Pages\ViewDocumentGeneration::route('/{record}'),
        ];
    }
}
