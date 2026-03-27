<?php

namespace Arseno25\DocxBuilder\Filament\Resources\DocumentGenerations\Pages;

use Arseno25\DocxBuilder\Filament\Resources\DocumentGenerations\DocumentGenerationResource;
use Filament\Resources\Pages\ListRecords;

class ListDocumentGenerations extends ListRecords
{
    protected static string $resource = DocumentGenerationResource::class;
}
