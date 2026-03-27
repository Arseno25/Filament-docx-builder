<?php

namespace Arseno25\DocxBuilder\Filament\Resources\DocumentGenerations\Tables;

use Arseno25\DocxBuilder\Filament\Resources\DocumentGenerations\DocumentGenerationResource;
use Arseno25\DocxBuilder\Models\DocumentGeneration;
use Arseno25\DocxBuilder\Services\GenerationService;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;

class DocumentGenerationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('template.code')
                    ->label('Template')
                    ->searchable(),
                TextColumn::make('version.version')
                    ->label('Version')
                    ->toggleable(),
                TextColumn::make('mode')->badge()->sortable(),
                TextColumn::make('status')->badge()->sortable(),
                TextColumn::make('output_filename')
                    ->label('Filename')
                    ->searchable(),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('retry')
                    ->label('Retry')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->visible(
                        fn(
                            DocumentGeneration $record,
                        ): bool => $record->status === 'failed' &&
                            is_array($record->payload_snapshot) &&
                            !empty($record->payload_snapshot),
                    )
                    ->authorize(
                        fn(DocumentGeneration $record): bool => (bool) auth()
                            ->user()
                            ?->can('retry', $record),
                    )
                    ->action(function (DocumentGeneration $record) {
                        /** @var GenerationService $service */
                        $service = app(GenerationService::class);
                        $new = $service->retryFailedGeneration($record);

                        return redirect(
                            DocumentGenerationResource::getUrl('view', [
                                'record' => $new,
                            ]),
                        );
                    }),
                Action::make('download')
                    ->label('Download')
                    ->visible(
                        fn(DocumentGeneration $record) => $record->status ===
                            'success',
                    )
                    ->authorize(
                        fn(DocumentGeneration $record): bool => (bool) auth()
                            ->user()
                            ?->can('download', $record),
                    )
                    ->action(function (DocumentGeneration $record) {
                        return Storage::disk($record->output_disk)->download(
                            $record->output_path,
                            $record->output_filename,
                        );
                    }),
            ]);
    }
}
