<?php

namespace Arseno25\DocxBuilder\Filament\Resources\DocumentGenerations\Pages;

use Arseno25\DocxBuilder\Filament\Resources\DocumentGenerations\DocumentGenerationResource;
use Arseno25\DocxBuilder\Models\DocumentGeneration;
use Arseno25\DocxBuilder\Services\GenerationService;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;

class ViewDocumentGeneration extends ViewRecord
{
    protected static string $resource = DocumentGenerationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('retry')
                ->label('Retry')
                ->color('warning')
                ->requiresConfirmation()
                ->visible(function (): bool {
                    $record = $this->getRecord();
                    if (!$record instanceof DocumentGeneration) {
                        return false;
                    }

                    return $record->status === 'failed' &&
                        is_array($record->payload_snapshot) &&
                        !empty($record->payload_snapshot);
                })
                ->authorize(function (): bool {
                    $record = $this->getRecord();

                    return $record instanceof DocumentGeneration &&
                        (bool) auth()->user()?->can('retry', $record);
                })
                ->action(function () {
                    /** @var DocumentGeneration $record */
                    $record = $this->getRecord();

                    /** @var GenerationService $service */
                    $service = app(GenerationService::class);
                    $new = $service->retryFailedGeneration($record);

                    $this->redirect(
                        static::getResource()::getUrl('view', [
                            'record' => $new,
                        ]),
                    );
                }),
            Action::make('back')
                ->label('Back')
                ->url(static::getResource()::getUrl('index')),
        ];
    }
}
