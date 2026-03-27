<?php

namespace Arseno25\DocxBuilder\Filament\Resources\DocumentTemplates\RelationManagers;

use Arseno25\DocxBuilder\Models\DocumentNumberSequence;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TemplateSequencesRelationManager extends RelationManager
{
    protected static string $relationship = 'sequences';

    protected static ?string $title = 'Numbering / Sequences';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('key')
                ->required()
                ->maxLength(64)
                ->helperText('Example: document_number')
                ->scopedUnique(
                    model: DocumentNumberSequence::class,
                    column: 'key',
                    modifyQueryUsing: fn(
                        Builder $query,
                    ): Builder => $query->where(
                        'template_id',
                        $this->getOwnerRecord()->getKey(),
                    ),
                ),
            TextInput::make('pattern')
                ->required()
                ->maxLength(255)
                ->helperText('Example: {seq:3}/SKD/{roman_month}/{year}'),
            Select::make('reset_policy')
                ->options([
                    'never' => 'Never',
                    'daily' => 'Daily',
                    'monthly' => 'Monthly',
                    'yearly' => 'Yearly',
                ])
                ->default('never')
                ->required(),
            TextInput::make('counter')
                ->numeric()
                ->default(0)
                ->helperText('Counter saat ini (ubah jika perlu).'),
            Toggle::make('is_active')->default(true),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('key')->searchable()->sortable(),
                TextColumn::make('pattern')->wrap()->toggleable(),
                TextColumn::make('reset_policy')->badge()->sortable(),
                TextColumn::make('counter')->sortable(),
                IconColumn::make('is_active')->boolean()->sortable(),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->headerActions([CreateAction::make()])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }
}
