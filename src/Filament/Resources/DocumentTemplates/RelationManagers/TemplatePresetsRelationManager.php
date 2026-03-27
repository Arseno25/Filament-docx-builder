<?php

namespace Arseno25\DocxBuilder\Filament\Resources\DocumentTemplates\RelationManagers;

use Arseno25\DocxBuilder\Models\DocumentPreset;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TemplatePresetsRelationManager extends RelationManager
{
    protected static string $relationship = 'presets';

    protected static ?string $title = 'Presets';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('key')
                ->required()
                ->maxLength(64)
                ->scopedUnique(
                    model: DocumentPreset::class,
                    column: 'key',
                    modifyQueryUsing: fn(
                        Builder $query,
                    ): Builder => $query->where(
                        'template_id',
                        $this->getOwnerRecord()->getKey(),
                    ),
                ),
            TextInput::make('label')->required()->maxLength(255),
            Select::make('type')
                ->options([
                    'text' => 'Text',
                    'image' => 'Image',
                    'json' => 'JSON',
                ])
                ->default('text')
                ->required()
                ->live(),
            Section::make('Value')->compact()->contained()->schema(
                fn(Get $get): array => match ($get('type')) {
                    'json' => [
                        \Filament\Forms\Components\KeyValue::make('value')
                            ->label('Value (JSON)')
                            ->nullable(),
                    ],
                    'image' => [
                        FileUpload::make('path')
                            ->label('Image file')
                            ->disk(config('docx-builder.output_disk', 'local'))
                            ->directory('docx-builder/presets')
                            ->preserveFilenames()
                            ->nullable(),
                        Hidden::make('disk')->default(
                            config('docx-builder.output_disk', 'local'),
                        ),
                    ],
                    default => [
                        Textarea::make('value')
                            ->label('Value')
                            ->rows(4)
                            ->nullable(),
                    ],
                },
            ),

            TextInput::make('sort_order')->numeric()->default(0),
            Toggle::make('is_active')->default(true),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('key')->searchable()->sortable(),
                TextColumn::make('label')->searchable()->sortable(),
                TextColumn::make('type')->badge()->sortable(),
                IconColumn::make('is_active')->boolean()->sortable(),
                TextColumn::make('sort_order')->sortable()->toggleable(),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->headerActions([CreateAction::make()])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }
}
