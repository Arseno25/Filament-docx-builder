<?php

namespace Arseno25\DocxBuilder\Filament\Resources\DocumentTemplates\RelationManagers;

use Arseno25\DocxBuilder\Models\DocumentTemplateField;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TemplateFieldsRelationManager extends RelationManager
{
    protected static string $relationship = 'fields';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Placeholder::make('placeholder_guide')
                ->label('Placeholder guide')
                ->content(
                    'Use placeholders like [doc.key] or {{doc.key}}. For sections: {{#if doc.key}}...{{/if}}, {{#each doc.items}}...{{/each}}. Use the version inspector to see placeholders found in the DOCX.',
                )
                ->columnSpanFull(),
            TextInput::make('label')->required()->maxLength(255),
            TextInput::make('key')
                ->required()
                ->maxLength(128)
                ->live(onBlur: true)
                ->afterStateUpdated(function (
                    Set $set,
                    Get $get,
                    ?string $state,
                ): void {
                    if (filled($get('placeholder_tag'))) {
                        return;
                    }

                    if (blank($state)) {
                        return;
                    }

                    $set('placeholder_tag', "[doc.{$state}]");
                })
                ->scopedUnique(
                    model: DocumentTemplateField::class,
                    column: 'key',
                    modifyQueryUsing: fn(
                        Builder $query,
                    ): Builder => $query->where(
                        'template_id',
                        $this->getOwnerRecord()->getKey(),
                    ),
                ),
            Select::make('type')
                ->options([
                    'text' => 'Text',
                    'textarea' => 'Textarea',
                    'date' => 'Date',
                    'number' => 'Number',
                    'select' => 'Select',
                    'image' => 'Image',
                ])
                ->required(),
            Select::make('data_source_type')
                ->label('Data source type')
                ->options([
                    'manual' => 'Manual input',
                    'source_record' => 'Source record attribute',
                    'static_options' => 'Static options (select)',
                    'enum' => 'Enum (select)',
                    'model' => 'Model query (select)',
                ])
                ->default(
                    fn(Get $get): string => $get('type') === 'select'
                        ? 'static_options'
                        : 'source_record',
                )
                ->live()
                ->afterStateUpdated(function (Set $set, Get $get): void {
                    $type = (string) ($get('type') ?? '');
                    $sourceType = (string) ($get('data_source_type') ?? '');

                    if (
                        $type !== 'select' &&
                        in_array(
                            $sourceType,
                            ['static_options', 'enum', 'model'],
                            true,
                        )
                    ) {
                        $set('data_source_type', 'source_record');
                        $sourceType = 'source_record';
                    }

                    if ($sourceType === 'manual') {
                        $set('data_source_config', []);
                    }
                }),
            TextInput::make('group_name')->maxLength(128),
            TextInput::make('placeholder_tag')
                ->helperText('Example: [doc.full_name]')
                ->maxLength(255)
                ->required()
                ->scopedUnique(
                    model: DocumentTemplateField::class,
                    column: 'placeholder_tag',
                    modifyQueryUsing: fn(
                        Builder $query,
                    ): Builder => $query->where(
                        'template_id',
                        $this->getOwnerRecord()->getKey(),
                    ),
                ),
            Toggle::make('required')->default(false),
            Textarea::make('help_text')->columnSpanFull(),
            TextInput::make('sort_order')->numeric()->default(0),
            KeyValue::make('default_value')
                ->label('Default value (JSON)')
                ->nullable(),
            TagsInput::make('validation_rules')
                ->label('Validation rules')
                ->helperText('Example: required, max:255')
                ->nullable(),
            Section::make('Data source config')
                ->description(
                    'Configure how this field is prefilled or where its options come from.',
                )
                ->schema([
                    TextInput::make('data_source_config.attribute')
                        ->label('Source attribute')
                        ->helperText(
                            'Used when the template has a source model. Example: employee.name',
                        )
                        ->visible(
                            fn(Get $get): bool => (string) $get(
                                'data_source_type',
                            ) === 'source_record',
                        ),
                    TextInput::make('data_source_config.enum_class')
                        ->label('Enum class')
                        ->helperText('Example: App\\Enums\\Department')
                        ->visible(
                            fn(Get $get): bool => (string) $get(
                                'data_source_type',
                            ) === 'enum' && $get('type') === 'select',
                        ),
                    TextInput::make('data_source_config.model_class')
                        ->label('Model class')
                        ->helperText('Example: App\\Models\\User')
                        ->visible(
                            fn(Get $get): bool => (string) $get(
                                'data_source_type',
                            ) === 'model' && $get('type') === 'select',
                        ),
                    TextInput::make('data_source_config.value_attribute')
                        ->label('Value attribute')
                        ->default('id')
                        ->visible(
                            fn(Get $get): bool => (string) $get(
                                'data_source_type',
                            ) === 'model' && $get('type') === 'select',
                        ),
                    TextInput::make('data_source_config.label_attribute')
                        ->label('Label attribute')
                        ->default('name')
                        ->visible(
                            fn(Get $get): bool => (string) $get(
                                'data_source_type',
                            ) === 'model' && $get('type') === 'select',
                        ),
                    TextInput::make('data_source_config.order_by')
                        ->label('Order by')
                        ->visible(
                            fn(Get $get): bool => (string) $get(
                                'data_source_type',
                            ) === 'model' && $get('type') === 'select',
                        ),
                    TextInput::make('data_source_config.limit')
                        ->label('Limit')
                        ->numeric()
                        ->minValue(1)
                        ->default(200)
                        ->visible(
                            fn(Get $get): bool => (string) $get(
                                'data_source_type',
                            ) === 'model' && $get('type') === 'select',
                        ),
                ])
                ->columns(2)
                ->collapsed(),
            Section::make('Transform rules')
                ->description(
                    'Transform the field value before rendering the DOCX (JSON array).',
                )
                ->schema([
                    Textarea::make('transform_rules')
                        ->label('Rules (JSON)')
                        ->helperText(
                            'Example: [{"type":"trim"},{"type":"upper"}]',
                        )
                        ->rules(['nullable', 'json'])
                        ->formatStateUsing(function ($state): string {
                            if (blank($state)) {
                                return '';
                            }

                            if (is_string($state)) {
                                return $state;
                            }

                            if (!is_array($state)) {
                                return '';
                            }

                            $json = json_encode(
                                $state,
                                JSON_PRETTY_PRINT |
                                    JSON_UNESCAPED_SLASHES |
                                    JSON_UNESCAPED_UNICODE,
                            );

                            return $json === false ? '' : $json;
                        })
                        ->dehydrateStateUsing(function ($state): array {
                            if (blank($state)) {
                                return [];
                            }

                            if (!is_string($state)) {
                                return [];
                            }

                            $decoded = json_decode($state, true);

                            return is_array($decoded) ? $decoded : [];
                        })
                        ->rows(6)
                        ->columnSpanFull(),
                ])
                ->collapsed(),
            Section::make('Select options')
                ->description('Options for select fields (value => label).')
                ->schema([
                    KeyValue::make('data_source_config.options')
                        ->label('Options')
                        ->nullable(),
                ])
                ->visible(function (Get $get): bool {
                    if ($get('type') !== 'select') {
                        return false;
                    }

                    $t =
                        (string) ($get('data_source_type') ?? 'static_options');

                    return $t === 'static_options';
                })
                ->collapsed(),
            Section::make('Visibility rules')
                ->description('Show/hide this field based on another field.')
                ->schema([
                    TextInput::make('visibility_rules.when')
                        ->label('When field key')
                        ->maxLength(128),
                    Select::make('visibility_rules.operator')
                        ->label('Operator')
                        ->options([
                            'filled' => 'Filled',
                            'blank' => 'Blank',
                            'equals' => 'Equals',
                            'not_equals' => 'Not equals',
                        ])
                        ->default('filled'),
                    TextInput::make('visibility_rules.value')
                        ->label('Value')
                        ->maxLength(255),
                ])
                ->columns(3)
                ->collapsed(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->reorderable('sort_order')
            ->columns([
                TextColumn::make('group_name')
                    ->label('Group')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('label')->searchable(),
                TextColumn::make('key')->searchable(),
                TextColumn::make('type')->badge(),
                TextColumn::make('placeholder_tag')
                    ->label('Placeholder')
                    ->toggleable(),
                IconColumn::make('required')->boolean(),
            ])
            ->headerActions([CreateAction::make()])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }
}
