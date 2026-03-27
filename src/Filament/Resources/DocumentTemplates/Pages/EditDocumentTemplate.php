<?php

namespace Arseno25\DocxBuilder\Filament\Resources\DocumentTemplates\Pages;

use Arseno25\DocxBuilder\Filament\Resources\DocumentTemplates\DocumentTemplateResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditDocumentTemplate extends EditRecord
{
    protected static string $resource = DocumentTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
