<?php

namespace Arseno25\DocxBuilder\Filament\Resources\DocumentTemplates\RelationManagers;

use Arseno25\DocxBuilder\Models\DocumentTemplateVersion;
use Arseno25\DocxBuilder\Services\TemplateVersionService;
use Arseno25\DocxBuilder\Support\TemplateValidator;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

class TemplateVersionsRelationManager extends RelationManager
{
    protected static string $relationship = 'versions';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('version')->required()->maxLength(64),
            FileUpload::make('source_path')
                ->label('DOCX file')
                ->required()
                ->disk(config('docx-builder.template_disk', 'local'))
                ->directory('docx-builder/templates')
                ->preserveFilenames()
                ->acceptedFileTypes([
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                ]),
            Textarea::make('changelog')->columnSpanFull(),
            Hidden::make('source_disk')->default(
                config('docx-builder.template_disk', 'local'),
            ),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('version')->sortable()->searchable(),
                IconColumn::make('is_active')->boolean()->label('Active'),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('setActive')
                    ->label('Set Active')
                    ->requiresConfirmation()
                    ->action(function (DocumentTemplateVersion $record): void {
                        /** @var TemplateVersionService $service */
                        $service = app(TemplateVersionService::class);
                        $service->setActive($this->getOwnerRecord(), $record);
                    }),
                Action::make('rollback')
                    ->label('Rollback to previous')
                    ->requiresConfirmation()
                    ->visible(
                        fn(
                            DocumentTemplateVersion $record,
                        ): bool => (bool) $record->is_active,
                    )
                    ->action(function (): void {
                        /** @var TemplateVersionService $service */
                        $service = app(TemplateVersionService::class);
                        $service->rollbackToPrevious($this->getOwnerRecord());
                    }),
                Action::make('inspect')
                    ->label('Inspect placeholders')
                    ->modalHeading(
                        fn(
                            DocumentTemplateVersion $record,
                        ): string => 'Template inspector — ' .
                            (string) $record->version,
                    )
                    ->modalDescription(
                        'Shows placeholders found in this DOCX version and compares them to the mapped fields.',
                    )
                    ->modalContent(function (
                        DocumentTemplateVersion $record,
                    ): HtmlString {
                        $template = $this->getOwnerRecord();

                        /** @var TemplateValidator $validator */
                        $validator = app(TemplateValidator::class);

                        try {
                            $templateTags = $validator->extractPlaceholderTags(
                                $record,
                            );
                        } catch (\Throwable $e) {
                            $msg = e($e->getMessage());

                            return new HtmlString(
                                '<div class="text-sm text-danger-600">Unable to inspect template placeholders: ' .
                                    $msg .
                                    '</div>',
                            );
                        }

                        $schemaTags = $template
                            ->fields()
                            ->pluck('placeholder_tag')
                            ->filter(fn($tag) => filled($tag))
                            ->map(fn($tag) => (string) $tag)
                            ->values()
                            ->all();

                        sort($schemaTags);

                        $missingInDocx = array_values(
                            array_diff($schemaTags, $templateTags),
                        );
                        $unmappedInSchema = array_values(
                            array_diff($templateTags, $schemaTags),
                        );

                        $html = '';

                        $html .= '<div class="space-y-4 text-sm">';
                        $html .=
                            '<div class="grid grid-cols-1 gap-3 md:grid-cols-3">';
                        $html .=
                            '<div class="rounded-lg border bg-white p-3 dark:bg-white/5"><div class="text-xs text-gray-500">Placeholders in DOCX</div><div class="text-lg font-semibold">' .
                            e((string) count($templateTags)) .
                            '</div></div>';
                        $html .=
                            '<div class="rounded-lg border bg-white p-3 dark:bg-white/5"><div class="text-xs text-gray-500">Mapped placeholders (schema)</div><div class="text-lg font-semibold">' .
                            e((string) count($schemaTags)) .
                            '</div></div>';
                        $html .=
                            '<div class="rounded-lg border bg-white p-3 dark:bg-white/5"><div class="text-xs text-gray-500">Unmapped placeholders</div><div class="text-lg font-semibold">' .
                            e((string) count($unmappedInSchema)) .
                            '</div></div>';
                        $html .= '</div>';

                        $html .= $this->renderTagList(
                            'Placeholders found in this DOCX version',
                            $templateTags,
                        );

                        $html .= $this->renderTagList(
                            'Mapped placeholders (template fields)',
                            $schemaTags,
                        );

                        $html .= $this->renderTagList(
                            'Schema placeholders not found in this DOCX',
                            $missingInDocx,
                            tone: 'warning',
                        );

                        $html .= $this->renderTagList(
                            'DOCX placeholders without a mapped field',
                            $unmappedInSchema,
                            tone: 'danger',
                        );

                        $html .= '</div>';

                        return new HtmlString($html);
                    }),
            ])
            ->headerActions([
                CreateAction::make()->mutateFormDataUsing(function (
                    array $data,
                ): array {
                    /** @var TemplateVersionService $service */
                    $service = app(TemplateVersionService::class);

                    $data['uploaded_by'] = auth()->id();
                    $data['schema_snapshot'] = $service->makeSchemaSnapshot(
                        $this->getOwnerRecord(),
                    );

                    return $data;
                }),
            ]);
    }

    /**
     * @param  array<int, string>  $tags
     */
    private function renderTagList(
        string $title,
        array $tags,
        string $tone = 'neutral',
    ): string {
        $toneClasses = match ($tone) {
            'warning'
                => 'border-warning-200 bg-warning-50 dark:border-warning-500/30 dark:bg-warning-500/10',
            'danger'
                => 'border-danger-200 bg-danger-50 dark:border-danger-500/30 dark:bg-danger-500/10',
            default
                => 'border-gray-200 bg-gray-50 dark:border-white/10 dark:bg-white/5',
        };

        $html = '<div class="rounded-lg border ' . $toneClasses . ' p-3">';
        $html .=
            '<div class="mb-2 text-xs font-medium text-gray-600 dark:text-gray-300">' .
            e($title) .
            '</div>';

        if (empty($tags)) {
            $html .= '<div class="text-xs text-gray-500">None</div>';
            $html .= '</div>';
            return $html;
        }

        $html .=
            '<div class="max-h-56 overflow-auto rounded-md border border-gray-200 bg-white p-2 text-xs dark:border-white/10 dark:bg-black/20">';

        foreach ($tags as $tag) {
            $html .= '<div class="font-mono">' . e($tag) . '</div>';
        }

        $html .= '</div>';
        $html .= '</div>';

        return $html;
    }
}
