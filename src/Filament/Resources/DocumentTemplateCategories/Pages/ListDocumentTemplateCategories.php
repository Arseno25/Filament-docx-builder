<?php

namespace Arseno25\DocxBuilder\Filament\Resources\DocumentTemplateCategories\Pages;

use Arseno25\DocxBuilder\Filament\Resources\DocumentTemplateCategories\DocumentTemplateCategoryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDocumentTemplateCategories extends ListRecords
{
    protected static string $resource = DocumentTemplateCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
