<?php

namespace Arseno25\DocxBuilder\Filament\Resources\DocumentTemplates\Tables;

use Arseno25\DocxBuilder\Models\DocumentTemplate;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DocumentTemplatesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->searchable()->sortable(),
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('category.name')->label('Category')->sortable(),
                TextColumn::make('status')->badge()->sortable(),
                IconColumn::make('is_archived')->boolean()->label('Archived'),
                TextColumn::make('updated_at')->dateTime()->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('duplicate')
                    ->label('Duplicate')
                    ->action(function (DocumentTemplate $record): void {
                        $copy = $record->replicate();
                        $copy->code = $record->code . '_copy_' . now()->format('YmdHis');
                        $copy->status = 'draft';
                        $copy->active_version_id = null;
                        $copy->is_archived = false;
                        $copy->push();
                    }),
                Action::make('archive')
                    ->label(fn (DocumentTemplate $record) => $record->is_archived ? 'Unarchive' : 'Archive')
                    ->requiresConfirmation()
                    ->action(function (DocumentTemplate $record): void {
                        $record->is_archived = ! $record->is_archived;
                        $record->save();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
