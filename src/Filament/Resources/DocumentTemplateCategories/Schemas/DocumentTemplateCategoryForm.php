<?php

namespace Arseno25\DocxBuilder\Filament\Resources\DocumentTemplateCategories\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class DocumentTemplateCategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('code')
                ->required()
                ->maxLength(64)
                ->unique(ignoreRecord: true),
            TextInput::make('name')
                ->required()
                ->maxLength(255),
            Textarea::make('description')->columnSpanFull(),
            TextInput::make('sort_order')->numeric()->default(0),
            Toggle::make('is_active')->default(true),
        ]);
    }
}
