<?php

namespace Arseno25\DocxBuilder\Filament\Resources\DocumentTemplates\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class DocumentTemplateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(2)->schema([
                TextInput::make('code')
                    ->required()
                    ->maxLength(64)
                    ->unique(ignoreRecord: true),
                TextInput::make('name')->required()->maxLength(255),
                Select::make('category_id')
                    ->relationship('category', 'name')
                    ->searchable()
                    ->preload(),
                Select::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'active' => 'Active',
                        'archived' => 'Archived',
                    ])
                    ->default('draft')
                    ->required(),
                Select::make('visibility')
                    ->options([
                        'internal' => 'Internal',
                        'restricted' => 'Restricted',
                    ])
                    ->default('internal')
                    ->required(),
            ]),
            Textarea::make('description')->columnSpanFull(),
            TextInput::make('output_filename_pattern')
                ->helperText('Example: SKD_{doc.number}_{doc.date}')
                ->maxLength(255),
            Select::make('payload_snapshot_policy')
                ->options([
                    'off' => 'Off',
                    'on_success' => 'On success',
                    'always' => 'Always',
                ])
                ->default('off')
                ->required(),
            Toggle::make('is_archived')->label('Archived')->default(false),
            Section::make('Model binding')
                ->description(
                    'Optional: prefill fields from a source Eloquent record.',
                )
                ->schema([
                    TextInput::make('source_model_class')
                        ->label('Source model class')
                        ->helperText('Example: App\\Models\\Customer')
                        ->maxLength(255),
                    TextInput::make('source_model_label_attribute')
                        ->label('Source label attribute')
                        ->helperText('Example: name')
                        ->maxLength(64),
                ])
                ->columns(2)
                ->collapsed(),
        ]);
    }
}
