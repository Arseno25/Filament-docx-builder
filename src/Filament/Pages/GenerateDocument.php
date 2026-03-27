<?php

namespace Arseno25\DocxBuilder\Filament\Pages;

use Arseno25\DocxBuilder\Filament\Resources\DocumentGenerations\DocumentGenerationResource;
use Arseno25\DocxBuilder\Models\DocumentNumberSequence;
use Arseno25\DocxBuilder\Models\DocumentPreset;
use Arseno25\DocxBuilder\Models\DocumentTemplate;
use Arseno25\DocxBuilder\Models\DocumentTemplateField;
use Arseno25\DocxBuilder\Services\DocxLayoutPreviewService;
use Arseno25\DocxBuilder\Services\DocxPreviewService;
use Arseno25\DocxBuilder\Services\GenerationService;
use Arseno25\DocxBuilder\Services\NumberSequenceService;
use Arseno25\DocxBuilder\Support\DocxBuilderPermissions;
use Arseno25\DocxBuilder\Support\FilenamePattern;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use RuntimeException;
use UnitEnum;

class GenerateDocument extends Page implements HasSchemas
{
    use InteractsWithSchemas;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-play';

    protected static string|UnitEnum|null $navigationGroup = 'Documents';

    protected string $view = 'docx-builder::filament.pages.generate-document';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        $user = auth()->user();

        if (!$user) {
            return false;
        }

        if (method_exists($user, 'hasPermissionTo')) {
            return (bool) $user->hasPermissionTo(
                DocxBuilderPermissions::GENERATE,
            );
        }

        return (bool) $user->can(DocxBuilderPermissions::GENERATE);
    }

    public function mount(): void
    {
        $this->form->fill([
            'mode' => 'final',
            'use_numbering' => true,
            'sequence_id' => null,
            'apply_presets' => true,
            'live_preview' => [
                'enabled' => (bool) config(
                    'docx-builder.preview.enabled_by_default',
                    true,
                ),
                'layout_enabled' => (bool) config(
                    'docx-builder.preview.layout.enabled_by_default',
                    false,
                ),
            ],
            'source_record_id' => null,
            'use_dummy_data' => false,
            'fields' => [],
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('template_id')
                    ->label('Template')
                    ->options(
                        fn() => DocumentTemplate::query()
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->all(),
                    )
                    ->searchable()
                    ->required()
                    ->live()
                    ->afterStateUpdated(function (
                        Select $component,
                        Set $set,
                        Get $get,
                    ): void {
                        $templateId = (int) $component->getState();

                        $set(
                            'fields',
                            $this->getInitialFieldState(
                                $templateId,
                                (bool) $get('apply_presets'),
                            ),
                        );
                        $set('source_record_id', null);
                        $set(
                            'sequence_id',
                            $this->getDefaultSequenceId($templateId),
                        );

                        $component
                            ->getContainer()
                            ->getComponent('dynamicFields')
                            ?->getChildSchema()
                            ?->fill();
                    }),
                Select::make('mode')
                    ->options([
                        'test' => 'Test',
                        'final' => 'Final',
                    ])
                    ->required()
                    ->live()
                    ->afterStateUpdated(function (
                        Set $set,
                        ?string $state,
                    ): void {
                        if ($state === 'test') {
                            $set('use_numbering', false);
                        }

                        if ($state === 'final') {
                            $set('use_numbering', true);
                        }
                    }),
                Toggle::make('use_dummy_data')
                    ->label('Use dummy data')
                    ->helperText(
                        'Automatically fill missing fields with dummy values for test runs.',
                    )
                    ->default(false)
                    ->visible(fn(Get $get): bool => $get('mode') === 'test'),
                Section::make('Source record')
                    ->schema([
                        TextInput::make('source_record_id')
                            ->label('Record ID')
                            ->helperText(
                                'Prefill fields from the selected model record.',
                            )
                            ->live()
                            ->afterStateUpdated(function (
                                Set $set,
                                Get $get,
                                mixed $state,
                            ): void {
                                $templateId = (int) $get('template_id');
                                if ($templateId <= 0) {
                                    return;
                                }

                                $template = DocumentTemplate::query()->find(
                                    $templateId,
                                );
                                if (!$template) {
                                    return;
                                }

                                $fields = (array) ($get('fields') ?? []);
                                $fields = $this->applySourceRecordIfNeeded(
                                    $template,
                                    $fields,
                                    $state,
                                );

                                $set('fields', $fields);
                            }),
                        Placeholder::make('source_record_label')
                            ->label('Record')
                            ->content(function (Get $get): string {
                                $templateId = (int) $get('template_id');
                                $recordId = $get('source_record_id');

                                if ($templateId <= 0 || blank($recordId)) {
                                    return '-';
                                }

                                $template = DocumentTemplate::query()->find(
                                    $templateId,
                                );
                                if (!$template) {
                                    return '-';
                                }

                                return $this->getSourceRecordLabel(
                                    $template,
                                    $recordId,
                                );
                            }),
                    ])
                    ->columns(2)
                    ->compact()
                    ->contained()
                    ->visible(
                        fn(Get $get): bool => $this->templateHasSourceModel(
                            (int) $get('template_id'),
                        ),
                    ),
                Section::make('Numbering')
                    ->schema([
                        Toggle::make('use_numbering')
                            ->label('Use numbering / sequence')
                            ->default(true)
                            ->live()
                            ->helperText(
                                'Only fills the number automatically when the number field is still empty.',
                            ),
                        Select::make('sequence_id')
                            ->label('Sequence')
                            ->options(
                                fn(
                                    Get $get,
                                ): array => $this->getSequenceOptions(
                                    (int) $get('template_id'),
                                ),
                            )
                            ->searchable()
                            ->preload()
                            ->live()
                            ->required(
                                fn(Get $get): bool => (bool) $get(
                                    'use_numbering',
                                ),
                            )
                            ->visible(
                                fn(Get $get): bool => (bool) $get(
                                    'use_numbering',
                                ),
                            ),
                    ])
                    ->columns(2)
                    ->compact()
                    ->contained()
                    ->visible(
                        fn(Get $get): bool => $get('mode') === 'final' &&
                            $this->templateHasSequences(
                                (int) $get('template_id'),
                            ),
                    ),
                Placeholder::make('filename_preview')
                    ->label('Preview filename')
                    ->content(
                        fn(Get $get): string => $this->getFilenamePreview($get),
                    )
                    ->columnSpanFull(),
                Section::make('Fields')
                    ->key('dynamicFields')
                    ->schema(
                        fn(Get $get): array => $this->getFieldGroupSections(
                            (int) $get('template_id'),
                        ),
                    )
                    ->visible(fn(Get $get): bool => filled($get('template_id')))
                    ->compact()
                    ->contained()
                    ->columns(2),
                Toggle::make('apply_presets')
                    ->label('Apply presets')
                    ->default(true)
                    ->live()
                    ->helperText(
                        'Fill empty fields using the template presets.',
                    ),
                Section::make('Live preview')
                    ->schema([
                        Toggle::make('live_preview.enabled')
                            ->label('Enable live preview')
                            ->helperText(
                                'Shows a text-only preview extracted from the rendered DOCX. Formatting is not shown.',
                            )
                            ->live(),
                        Toggle::make('live_preview.layout_enabled')
                            ->label('Enable layout preview (PDF)')
                            ->helperText(
                                'Renders a PDF preview with Word-like layout. Requires LibreOffice (headless) on the server.',
                            )
                            ->visible(
                                fn(): bool => (bool) config(
                                    'docx-builder.preview.layout.enabled',
                                    false,
                                ),
                            )
                            ->live(),
                        Placeholder::make('document_preview')
                            ->label('Document preview (text-only)')
                            ->content(
                                fn(
                                    Get $get,
                                ): HtmlString => $this->getDocumentPreviewHtml(
                                    $get,
                                ),
                            )
                            ->columnSpanFull(),
                        Placeholder::make('layout_preview')
                            ->label('Layout preview (PDF)')
                            ->content(
                                fn(
                                    Get $get,
                                ): HtmlString => $this->getLayoutPreviewHtml(
                                    $get,
                                ),
                            )
                            ->visible(function (Get $get): bool {
                                if (
                                    !(bool) config(
                                        'docx-builder.preview.layout.enabled',
                                        false,
                                    )
                                ) {
                                    return false;
                                }

                                return (bool) $get(
                                    'live_preview.layout_enabled',
                                );
                            })
                            ->columnSpanFull(),
                    ])
                    ->columns(1)
                    ->compact()
                    ->contained()
                    ->visible(fn(Get $get): bool => filled($get('template_id')))
                    ->columnSpanFull(),
                Section::make('Payload preview')
                    ->schema([
                        Placeholder::make('payload_preview')
                            ->label('Payload (JSON)')
                            ->content(
                                fn(
                                    Get $get,
                                ): HtmlString => $this->getPayloadPreviewHtml(
                                    $get,
                                ),
                            )
                            ->columnSpanFull(),
                    ])
                    ->columns(1)
                    ->compact()
                    ->contained()
                    ->collapsed()
                    ->visible(fn(Get $get): bool => filled($get('template_id')))
                    ->columnSpanFull(),
            ])
            ->statePath('data');
    }

    public function submit(?string $modeOverride = null): void
    {
        if (
            filled($modeOverride) &&
            in_array($modeOverride, ['test', 'final'], true)
        ) {
            $this->data['mode'] = $modeOverride;
            $this->data['use_numbering'] = $modeOverride === 'final';
        }

        if (
            ($this->data['mode'] ?? null) === 'test' &&
            ($this->data['use_dummy_data'] ?? false) === true
        ) {
            $templateId = (int) ($this->data['template_id'] ?? 0);
            if ($templateId > 0) {
                $template = DocumentTemplate::query()->find($templateId);
                if ($template) {
                    $this->data['fields'] = $this->fillDummyData(
                        $template,
                        is_array($this->data['fields'] ?? null)
                            ? $this->data['fields']
                            : [],
                    );
                }
            }
        }

        /** @var array<string, mixed> $state */
        $state = $this->form->getState();

        $template = DocumentTemplate::query()->findOrFail(
            (int) $state['template_id'],
        );
        $mode = (string) $state['mode'];

        /** @var array<string, mixed> $fields */
        $fields = is_array($state['fields'] ?? null) ? $state['fields'] : [];

        $renderLog = [
            'warnings' => [],
            'numbering' => null,
        ];

        $fields = $this->applyDefaultsAndPresets(
            $template,
            $fields,
            (bool) ($state['apply_presets'] ?? true),
        );

        try {
            $fields = $this->applySourceRecordIfNeeded(
                $template,
                $fields,
                $state['source_record_id'] ?? null,
                strict: true,
            );
        } catch (\Throwable $e) {
            Notification::make()->title($e->getMessage())->danger()->send();

            return;
        }

        $fields = $this->applyVisibilityRules($template, $fields);
        $fields = $this->applyTransformRules($template, $fields);

        $payload = [
            'doc' => $fields,
        ];

        try {
            $payload = $this->applyNumberingIfNeeded(
                $template,
                $payload,
                $state,
                $mode,
                $renderLog,
            );
        } catch (\Throwable $e) {
            Notification::make()->title($e->getMessage())->danger()->send();

            return;
        }

        $renderLog['warnings'] = $this->getEmptyPlaceholderWarnings(
            $template,
            $payload,
        );

        /** @var GenerationService $service */
        $service = app(GenerationService::class);

        try {
            $queueEnabled = (bool) config('docx-builder.queue.enabled', false);

            $generation = $queueEnabled
                ? $service->queue(
                    $template,
                    $payload,
                    $mode,
                    $renderLog,
                    sourceType: filled($template->source_model_class)
                        ? (string) $template->source_model_class
                        : null,
                    sourceId: filled($state['source_record_id'] ?? null)
                        ? (string) $state['source_record_id']
                        : null,
                )
                : $service->generate(
                    $template,
                    $payload,
                    $mode,
                    $renderLog,
                    sourceType: filled($template->source_model_class)
                        ? (string) $template->source_model_class
                        : null,
                    sourceId: filled($state['source_record_id'] ?? null)
                        ? (string) $state['source_record_id']
                        : null,
                );
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Failed to generate the document.')
                ->body($e->getMessage())
                ->danger()
                ->send();

            return;
        }

        Notification::make()
            ->title(
                $generation->status === 'queued'
                    ? 'Document generation has been queued.'
                    : 'Document generated successfully.',
            )
            ->success()
            ->send();

        if (!empty($renderLog['warnings'])) {
            Notification::make()
                ->title('Some placeholders are empty.')
                ->body(
                    'Review the fields that were left empty to avoid blank sections in the output document.',
                )
                ->warning()
                ->send();
        }

        $this->redirect(
            DocumentGenerationResource::getUrl('view', [
                'record' => $generation,
            ]),
        );
    }

    private function getDefaultSequenceId(int $templateId): ?int
    {
        if ($templateId <= 0) {
            return null;
        }

        $sequence = DocumentNumberSequence::query()
            ->where('template_id', $templateId)
            ->where('is_active', true)
            ->orderBy('key')
            ->first(['id']);

        return $sequence?->id;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $state
     * @param  array<string, mixed>  $renderLog
     * @return array<string, mixed>
     */
    private function applyNumberingIfNeeded(
        DocumentTemplate $template,
        array $payload,
        array $state,
        string $mode,
        array &$renderLog,
    ): array {
        if ($mode !== 'final') {
            return $payload;
        }

        if (!$this->templateHasSequences((int) $template->getKey())) {
            return $payload;
        }

        $useNumbering = (bool) ($state['use_numbering'] ?? false);
        if (!$useNumbering) {
            return $payload;
        }

        $sequenceId = $state['sequence_id'] ?? null;
        if (blank($sequenceId)) {
            throw new RuntimeException('No sequence has been selected.');
        }

        $sequence = DocumentNumberSequence::query()
            ->where('template_id', $template->getKey())
            ->where('is_active', true)
            ->whereKey($sequenceId)
            ->first();

        if (!$sequence) {
            throw new RuntimeException(
                'The selected sequence was not found or is not active.',
            );
        }

        $doc = (array) Arr::get($payload, 'doc', []);
        $current = $doc[$sequence->key] ?? null;

        if (blank($current)) {
            /** @var NumberSequenceService $svc */
            $svc = app(NumberSequenceService::class);
            $value = $svc->nextNumber($sequence);

            $doc[$sequence->key] = $value;
            Arr::set($payload, 'doc', $doc);

            $renderLog['numbering'] = [
                'sequence_id' => $sequence->getKey(),
                'key' => $sequence->key,
                'value' => $value,
                'applied' => true,
            ];
        } else {
            $renderLog['numbering'] = [
                'sequence_id' => $sequence->getKey(),
                'key' => $sequence->key,
                'value' => (string) $current,
                'applied' => false,
            ];
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, array{key: string, placeholder: string}>
     */
    private function getEmptyPlaceholderWarnings(
        DocumentTemplate $template,
        array $payload,
    ): array {
        $doc = (array) Arr::get($payload, 'doc', []);

        /** @var Collection<int, DocumentTemplateField> $fields */
        $fields = DocumentTemplateField::query()
            ->where('template_id', $template->getKey())
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return $fields
            ->filter(
                fn(DocumentTemplateField $field) => filled(
                    $field->placeholder_tag,
                ),
            )
            ->filter(
                fn(DocumentTemplateField $field) => blank(
                    $doc[$field->key] ?? null,
                ),
            )
            ->map(
                fn(DocumentTemplateField $field) => [
                    'key' => $field->key,
                    'placeholder' => (string) $field->placeholder_tag,
                ],
            )
            ->values()
            ->all();
    }

    private function templateHasSequences(int $templateId): bool
    {
        if ($templateId <= 0) {
            return false;
        }

        return DocumentNumberSequence::query()
            ->where('template_id', $templateId)
            ->where('is_active', true)
            ->exists();
    }

    /**
     * @return array<int|string, string>
     */
    private function getSequenceOptions(int $templateId): array
    {
        if ($templateId <= 0) {
            return [];
        }

        return DocumentNumberSequence::query()
            ->where('template_id', $templateId)
            ->where('is_active', true)
            ->orderBy('key')
            ->get()
            ->mapWithKeys(
                fn(DocumentNumberSequence $seq) => [
                    $seq->getKey() => "{$seq->key} — {$seq->pattern}",
                ],
            )
            ->all();
    }

    /**
     * @return array<int, \Filament\Schemas\Components\Component>
     */
    private function getFieldGroupSections(int $templateId): array
    {
        if ($templateId <= 0) {
            return [];
        }

        /** @var Collection<int, DocumentTemplateField> $fields */
        $fields = DocumentTemplateField::query()
            ->where('template_id', $templateId)
            ->orderByRaw('coalesce(group_name, \'\') asc')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        if ($fields->isEmpty()) {
            return [
                Section::make('No fields yet')
                    ->description(
                        'Add field definitions to the template first.',
                    )
                    ->schema([])
                    ->columns(1)
                    ->headerActions([])
                    ->footerActions([]),
            ];
        }

        $grouped = $fields->groupBy(
            fn(
                DocumentTemplateField $field,
            ): string => (string) ($field->group_name ?: 'General'),
        );

        return $grouped
            ->map(function (Collection $groupFields, string $groupName) {
                $components = $groupFields
                    ->map(
                        fn(
                            DocumentTemplateField $field,
                        ) => $this->makeFieldComponent($field),
                    )
                    ->filter()
                    ->values()
                    ->all();

                return Section::make($groupName)
                    ->schema($components)
                    ->columns(2)
                    ->compact()
                    ->contained();
            })
            ->values()
            ->all();
    }

    private function makeFieldComponent(
        DocumentTemplateField $field,
    ): ?\Filament\Schemas\Components\Component {
        $statePath = "fields.{$field->key}";

        $label = $field->label ?: Str::headline($field->key);
        $helpText = $field->help_text ?: null;
        $rules = is_array($field->validation_rules)
            ? $field->validation_rules
            : [];

        $component = match ($field->type) {
            'text' => TextInput::make($statePath),
            'textarea' => Textarea::make($statePath)->rows(4),
            'date' => DatePicker::make($statePath),
            'number' => TextInput::make($statePath)->numeric(),
            'select' => Select::make($statePath)
                ->options($this->getSelectOptions($field))
                ->searchable()
                ->preload(),
            'image' => FileUpload::make($statePath)
                ->disk(config('docx-builder.output_disk', 'local'))
                ->directory('docx-builder/uploads')
                ->preserveFilenames(),
            default => null,
        };

        if (!$component) {
            return null;
        }

        $debounce = (int) config('docx-builder.preview.debounce_ms', 700);
        if (method_exists($component, 'live') && $field->type !== 'image') {
            $component->live(debounce: $debounce);
        }

        return $component
            ->label($label)
            ->helperText($helpText)
            ->required((bool) $field->required)
            ->rules($rules)
            ->default($this->getDefaultValue($field))
            ->visible(
                fn(Get $get): bool => $this->isFieldVisibleInForm(
                    $field,
                    (array) ($get('fields') ?? []),
                ),
            );
    }

    /**
     * @return array<string, string>
     */
    private function getSelectOptions(DocumentTemplateField $field): array
    {
        $type = (string) ($field->data_source_type ?: 'static_options');

        if ($type === 'manual' || $type === 'source_record') {
            $type = 'static_options';
        }

        return match ($type) {
            'enum' => $this->getEnumSelectOptions($field),
            'model' => $this->getModelSelectOptions($field),
            default => $this->getStaticSelectOptions($field),
        };
    }

    /**
     * @return array<string, string>
     */
    private function getStaticSelectOptions(DocumentTemplateField $field): array
    {
        $options = $field->data_source_config['options'] ?? null;

        if (!is_array($options)) {
            return [];
        }

        $out = [];

        foreach ($options as $key => $label) {
            if (is_int($key)) {
                if (is_scalar($label)) {
                    $value = (string) $label;
                    $out[$value] = $value;
                }

                continue;
            }

            if (!is_scalar($key) || !is_scalar($label)) {
                continue;
            }

            $out[(string) $key] = (string) $label;
        }

        return $out;
    }

    /**
     * @return array<string, string>
     */
    private function getEnumSelectOptions(DocumentTemplateField $field): array
    {
        $class = (string) ($field->data_source_config['enum_class'] ?? '');
        $class = trim($class);

        if ($class === '' || !enum_exists($class)) {
            return [];
        }

        /** @var array<int, UnitEnum> $cases */
        $cases = $class::cases();

        $out = [];

        foreach ($cases as $case) {
            if ($case instanceof \BackedEnum) {
                $value = (string) $case->value;
                $out[$value] = $value;
                continue;
            }

            $out[$case->name] = Str::headline($case->name);
        }

        return $out;
    }

    /**
     * @return array<string, string>
     */
    private function getModelSelectOptions(DocumentTemplateField $field): array
    {
        $class = (string) ($field->data_source_config['model_class'] ?? '');
        $class = trim($class);

        if ($class === '' || !class_exists($class)) {
            return [];
        }

        if (!is_subclass_of($class, Model::class)) {
            return [];
        }

        $valueAttr =
            (string) ($field->data_source_config['value_attribute'] ?? 'id');
        $labelAttr =
            (string) ($field->data_source_config['label_attribute'] ?? 'name');
        $orderBy = (string) ($field->data_source_config['order_by'] ?? '');

        $limit = $field->data_source_config['limit'] ?? null;
        $limit = is_scalar($limit) ? (int) $limit : 200;
        $limit = $limit > 0 ? $limit : 200;

        /** @var \Illuminate\Database\Eloquent\Builder $query */
        $query = $class::query();

        if ($orderBy !== '') {
            $query->orderBy($orderBy);
        } else {
            $query->orderBy($labelAttr);
        }

        /** @var array<int|string, string> $pairs */
        $pairs = $query->limit($limit)->pluck($labelAttr, $valueAttr)->all();

        $out = [];

        foreach ($pairs as $k => $v) {
            $out[(string) $k] = (string) $v;
        }

        return $out;
    }

    private function getDocumentPreviewHtml(Get $get): HtmlString
    {
        $enabled = (bool) $get('live_preview.enabled');
        if (!$enabled) {
            return new HtmlString(
                '<div class="text-sm text-gray-500">Live preview is disabled.</div>',
            );
        }

        $templateId = (int) $get('template_id');
        if ($templateId <= 0) {
            return new HtmlString(
                '<div class="text-sm text-gray-500">Select a template to see a preview.</div>',
            );
        }

        $template = DocumentTemplate::query()->find($templateId);
        if (!$template) {
            return new HtmlString(
                '<div class="text-sm text-gray-500">Template not found.</div>',
            );
        }

        $version = $template->activeVersion()->first();
        if (!$version) {
            return new HtmlString(
                '<div class="text-sm text-gray-500">Template has no active version.</div>',
            );
        }

        try {
            $payload = $this->buildPreviewPayload($template, $get);

            $maxChars = (int) config('docx-builder.preview.max_chars', 12000);

            /** @var DocxPreviewService $svc */
            $svc = app(DocxPreviewService::class);
            $text = $svc->previewText($version, $payload, $maxChars);
        } catch (\Throwable $e) {
            $msg = e($e->getMessage());

            return new HtmlString(
                '<div class="text-sm text-danger-600">Preview unavailable: ' .
                    $msg .
                    '</div>',
            );
        }

        if (blank($text)) {
            return new HtmlString(
                '<div class="text-sm text-gray-500">No previewable text was found in the template.</div>',
            );
        }

        $safe = e($text);

        return new HtmlString(
            '<pre class="rounded-lg border bg-gray-50 p-3 text-xs leading-relaxed text-gray-800 whitespace-pre-wrap break-words dark:bg-white/5 dark:text-gray-100 max-h-96 overflow-auto">' .
                $safe .
                '</pre>',
        );
    }

    private function getPayloadPreviewHtml(Get $get): HtmlString
    {
        $templateId = (int) $get('template_id');
        if ($templateId <= 0) {
            return new HtmlString(
                '<div class="text-sm text-gray-500">Select a template to see a payload preview.</div>',
            );
        }

        $template = DocumentTemplate::query()->find($templateId);
        if (!$template) {
            return new HtmlString(
                '<div class="text-sm text-gray-500">Template not found.</div>',
            );
        }

        try {
            $payload = $this->buildPreviewPayload($template, $get);
        } catch (\Throwable $e) {
            $msg = e($e->getMessage());

            return new HtmlString(
                '<div class="text-sm text-danger-600">Payload preview unavailable: ' .
                    $msg .
                    '</div>',
            );
        }

        $json = json_encode(
            $payload,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );

        $json = $json === false ? '{}' : $json;

        return new HtmlString(
            '<pre class="rounded-lg border bg-gray-50 p-3 text-xs leading-relaxed text-gray-800 whitespace-pre-wrap break-words dark:bg-white/5 dark:text-gray-100 max-h-96 overflow-auto">' .
                e($json) .
                '</pre>',
        );
    }

    private function getLayoutPreviewHtml(Get $get): HtmlString
    {
        if (!(bool) config('docx-builder.preview.layout.enabled', false)) {
            return new HtmlString(
                '<div class="text-sm text-gray-500">Layout preview is disabled.</div>',
            );
        }

        $templateId = (int) $get('template_id');
        if ($templateId <= 0) {
            return new HtmlString(
                '<div class="text-sm text-gray-500">Select a template to see a layout preview.</div>',
            );
        }

        $template = DocumentTemplate::query()->find($templateId);
        if (!$template) {
            return new HtmlString(
                '<div class="text-sm text-gray-500">Template not found.</div>',
            );
        }

        $version = $template->activeVersion()->first();
        if (!$version) {
            return new HtmlString(
                '<div class="text-sm text-gray-500">Template has no active version.</div>',
            );
        }

        try {
            $payload = $this->buildPreviewPayload($template, $get);

            /** @var DocxLayoutPreviewService $svc */
            $svc = app(DocxLayoutPreviewService::class);
            $url = $svc->previewPdfUrl($version, $payload);
        } catch (\Throwable $e) {
            $msg = e($e->getMessage());

            return new HtmlString(
                '<div class="text-sm text-danger-600">Layout preview unavailable: ' .
                    $msg .
                    '</div>',
            );
        }

        $safeUrl = e($url);

        return new HtmlString(
            '<div class="space-y-2">' .
                '<div class="text-xs text-gray-500">If the PDF does not render in your browser, use the link below.</div>' .
                '<div class="rounded-lg border bg-white dark:bg-black/20 overflow-hidden">' .
                '<iframe class="w-full h-[40rem]" src="' .
                $safeUrl .
                '" loading="lazy"></iframe>' .
                '</div>' .
                '<a class="text-sm underline text-primary-600" href="' .
                $safeUrl .
                '" target="_blank" rel="noopener noreferrer">Open PDF preview</a>' .
                '</div>',
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPreviewPayload(
        DocumentTemplate $template,
        Get $get,
    ): array {
        $mode = (string) ($get('mode') ?: 'final');

        $fields = $get('fields');
        $fields = is_array($fields) ? $fields : [];

        $applyPresets = (bool) ($get('apply_presets') ?? true);

        $fields = $this->applyDefaultsAndPresets(
            $template,
            $fields,
            $applyPresets,
        );

        $fields = $this->applySourceRecordIfNeeded(
            $template,
            $fields,
            $get('source_record_id'),
            strict: false,
        );

        $fields = $this->applyVisibilityRules($template, $fields);

        if ($mode === 'test' && (bool) ($get('use_dummy_data') ?? false)) {
            $fields = $this->fillDummyData($template, $fields);
        }

        $fields = $this->applyTransformRules($template, $fields);

        $payload = ['doc' => $fields];

        return $this->applyNumberingPreviewIfNeeded($template, $payload, $get);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function applyNumberingPreviewIfNeeded(
        DocumentTemplate $template,
        array $payload,
        Get $get,
    ): array {
        $mode = (string) ($get('mode') ?: 'final');
        if ($mode !== 'final') {
            return $payload;
        }

        if (!$this->templateHasSequences((int) $template->getKey())) {
            return $payload;
        }

        $useNumbering = (bool) ($get('use_numbering') ?? false);
        if (!$useNumbering) {
            return $payload;
        }

        $sequenceId = $get('sequence_id');
        if (blank($sequenceId)) {
            return $payload;
        }

        $sequence = DocumentNumberSequence::query()
            ->where('template_id', $template->getKey())
            ->where('is_active', true)
            ->whereKey($sequenceId)
            ->first();

        if (!$sequence) {
            return $payload;
        }

        $doc = (array) Arr::get($payload, 'doc', []);
        $current = $doc[$sequence->key] ?? null;

        if (blank($current)) {
            /** @var NumberSequenceService $svc */
            $svc = app(NumberSequenceService::class);
            $doc[$sequence->key] = $svc->peekNextNumber($sequence);
            Arr::set($payload, 'doc', $doc);
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $fields
     * @return array<string, mixed>
     */
    private function applyTransformRules(
        DocumentTemplate $template,
        array $fields,
    ): array {
        /** @var Collection<int, DocumentTemplateField> $schemaFields */
        $schemaFields = DocumentTemplateField::query()
            ->where('template_id', $template->getKey())
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['key', 'transform_rules']);

        foreach ($schemaFields as $schemaField) {
            $key = (string) ($schemaField->key ?? '');
            if ($key === '') {
                continue;
            }

            $rules = $schemaField->transform_rules;
            if (!is_array($rules) || empty($rules)) {
                continue;
            }

            if (!array_key_exists($key, $fields)) {
                continue;
            }

            $value = $fields[$key];
            if (blank($value)) {
                continue;
            }

            $fields[$key] = $this->applyTransformSteps($value, $rules);
        }

        return $fields;
    }

    /**
     * @param  array<int, mixed>  $rules
     */
    private function applyTransformSteps(mixed $value, array $rules): mixed
    {
        $current = $value;

        foreach ($rules as $rule) {
            $step = $this->normalizeTransformStep($rule);
            if (!$step) {
                continue;
            }

            $current = $this->applyTransformStep($current, $step);
        }

        return $current;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function normalizeTransformStep(mixed $rule): ?array
    {
        if (is_string($rule) && $rule !== '') {
            return ['type' => $rule];
        }

        if (!is_array($rule)) {
            return null;
        }

        $type = $rule['type'] ?? null;
        if (!is_string($type) || $type === '') {
            return null;
        }

        return $rule;
    }

    /**
     * @param  array<string, mixed>  $rule
     */
    private function applyTransformStep(mixed $value, array $rule): mixed
    {
        $type = (string) $rule['type'];

        if ($value instanceof \DateTimeInterface) {
            $value = $value->format('Y-m-d');
        }

        if (!is_scalar($value)) {
            return $value;
        }

        $string = (string) $value;

        return match ($type) {
            'trim' => trim($string),
            'upper' => mb_strtoupper($string),
            'lower' => mb_strtolower($string),
            'title' => Str::title($string),
            'replace' => $this->transformReplace($string, $rule),
            'prefix' => (string) ($rule['value'] ?? '') . $string,
            'suffix' => $string . (string) ($rule['value'] ?? ''),
            'pad_left' => $this->transformPadLeft($string, $rule),
            'date_format' => $this->transformDateFormat($string, $rule),
            'number_format' => $this->transformNumberFormat($string, $rule),
            default => $value,
        };
    }

    /**
     * @param  array<string, mixed>  $rule
     */
    private function transformReplace(string $value, array $rule): string
    {
        $search = $rule['search'] ?? null;
        $replace = $rule['replace'] ?? '';

        if (!is_scalar($search)) {
            return $value;
        }

        return str_replace((string) $search, (string) $replace, $value);
    }

    /**
     * @param  array<string, mixed>  $rule
     */
    private function transformPadLeft(string $value, array $rule): string
    {
        $length = $rule['length'] ?? null;
        if (!is_scalar($length)) {
            return $value;
        }

        $len = (int) $length;
        if ($len <= 0) {
            return $value;
        }

        $pad = (string) ($rule['pad'] ?? '0');
        $padChar = $pad === '' ? '0' : mb_substr($pad, 0, 1);

        return str_pad($value, $len, $padChar, STR_PAD_LEFT);
    }

    /**
     * @param  array<string, mixed>  $rule
     */
    private function transformDateFormat(string $value, array $rule): string
    {
        $format = $rule['format'] ?? null;
        if (!is_scalar($format)) {
            return $value;
        }

        $format = (string) $format;
        if ($format === '') {
            return $value;
        }

        try {
            $inputFormat = $rule['input_format'] ?? null;
            $dt =
                is_scalar($inputFormat) && (string) $inputFormat !== ''
                    ? Carbon::createFromFormat((string) $inputFormat, $value)
                    : Carbon::parse($value);

            return $dt->format($format);
        } catch (\Throwable) {
            return $value;
        }
    }

    /**
     * @param  array<string, mixed>  $rule
     */
    private function transformNumberFormat(string $value, array $rule): string
    {
        if (!is_numeric($value)) {
            return $value;
        }

        $decimals = (int) ($rule['decimals'] ?? 0);
        $decimalSeparator = (string) ($rule['decimal_separator'] ?? '.');
        $thousandsSeparator = (string) ($rule['thousands_separator'] ?? ',');

        return number_format(
            (float) $value,
            max(0, $decimals),
            $decimalSeparator,
            $thousandsSeparator,
        );
    }

    private function getFilenamePreview(Get $get): string
    {
        $templateId = (int) $get('template_id');
        if ($templateId <= 0) {
            return '-';
        }

        $template = DocumentTemplate::query()->find($templateId);
        if (!$template) {
            return '-';
        }

        $fields = (array) ($get('fields') ?? []);

        $pattern =
            $template->output_filename_pattern ?:
            'document_{doc.number}_{doc.date}';

        return FilenamePattern::make(
            $pattern,
            ['doc' => $fields],
            $template->code ?: 'document',
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function getInitialFieldState(
        int $templateId,
        bool $applyPresets,
    ): array {
        if ($templateId <= 0) {
            return [];
        }

        $template = DocumentTemplate::query()->find($templateId);
        if (!$template) {
            return [];
        }

        return $this->applyDefaultsAndPresets($template, [], $applyPresets);
    }

    /**
     * @param  array<string, mixed>  $fields
     * @return array<string, mixed>
     */
    private function applyDefaultsAndPresets(
        DocumentTemplate $template,
        array $fields,
        bool $applyPresets,
    ): array {
        /** @var Collection<int, DocumentTemplateField> $schemaFields */
        $schemaFields = DocumentTemplateField::query()
            ->where('template_id', $template->getKey())
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        foreach ($schemaFields as $schemaField) {
            if (!filled($schemaField->key)) {
                continue;
            }

            if (
                array_key_exists($schemaField->key, $fields) &&
                filled($fields[$schemaField->key])
            ) {
                continue;
            }

            $default = $this->getDefaultValue($schemaField);
            if (filled($default)) {
                $fields[$schemaField->key] = $default;
            }
        }

        if (!$applyPresets) {
            return $fields;
        }

        /** @var Collection<int, DocumentPreset> $presets */
        $presets = DocumentPreset::query()
            ->where('template_id', $template->getKey())
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        foreach ($presets as $preset) {
            if (!filled($preset->key)) {
                continue;
            }

            if (
                array_key_exists($preset->key, $fields) &&
                filled($fields[$preset->key])
            ) {
                continue;
            }

            $fields[$preset->key] = match ($preset->type) {
                'image' => [
                    'disk' =>
                        $preset->disk ?:
                        (string) config('docx-builder.output_disk', 'local'),
                    'path' => $preset->path,
                ],
                'json' => $preset->value,
                default => (string) ($preset->value['text'] ??
                    ($preset->value ?? '')),
            };
        }

        return $fields;
    }

    private function templateHasSourceModel(int $templateId): bool
    {
        if ($templateId <= 0) {
            return false;
        }

        return DocumentTemplate::query()
            ->whereKey($templateId)
            ->whereNotNull('source_model_class')
            ->where('source_model_class', '!=', '')
            ->exists();
    }

    /**
     * @param  array<string, mixed>  $fields
     * @return array<string, mixed>
     */
    private function applySourceRecordIfNeeded(
        DocumentTemplate $template,
        array $fields,
        mixed $recordId,
        bool $strict = false,
    ): array {
        if (blank($recordId)) {
            return $fields;
        }

        $modelClass = $this->resolveSourceModelClass($template);
        if (!$modelClass) {
            if ($strict) {
                throw new RuntimeException(
                    'This template does not have a valid source model configured.',
                );
            }

            return $fields;
        }

        /** @var Model|null $record */
        $record = $modelClass::query()->find($recordId);
        if (!$record) {
            if ($strict) {
                throw new RuntimeException('Source record was not found.');
            }

            return $fields;
        }

        /** @var Collection<int, DocumentTemplateField> $schemaFields */
        $schemaFields = DocumentTemplateField::query()
            ->where('template_id', $template->getKey())
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        foreach ($schemaFields as $schemaField) {
            if (!filled($schemaField->key)) {
                continue;
            }

            $dataSourceType = (string) ($schemaField->data_source_type ?: '');
            if ($dataSourceType !== '' && $dataSourceType !== 'source_record') {
                continue;
            }

            if (filled($fields[$schemaField->key] ?? null)) {
                continue;
            }

            $attribute =
                (string) ($schemaField->data_source_config['attribute'] ??
                    $schemaField->key);

            $value = data_get($record, $attribute);

            if ($value instanceof \DateTimeInterface) {
                $value = $value->format('Y-m-d');
            }

            if (filled($value)) {
                $fields[$schemaField->key] = $value;
            }
        }

        return $fields;
    }

    private function getSourceRecordLabel(
        DocumentTemplate $template,
        mixed $recordId,
    ): string {
        $modelClass = $this->resolveSourceModelClass($template);
        if (!$modelClass) {
            return '-';
        }

        /** @var Model|null $record */
        $record = $modelClass::query()->find($recordId);
        if (!$record) {
            return '-';
        }

        $attribute = $template->source_model_label_attribute ?: null;
        if (filled($attribute)) {
            $label = data_get($record, (string) $attribute);
            if (filled($label)) {
                return (string) $label;
            }
        }

        return (string) $record->getKey();
    }

    /**
     * @return class-string<Model>|null
     */
    private function resolveSourceModelClass(
        DocumentTemplate $template,
    ): ?string {
        $class = trim((string) ($template->source_model_class ?: ''));
        if ($class === '') {
            return null;
        }

        if (!class_exists($class)) {
            return null;
        }

        if (!is_subclass_of($class, Model::class)) {
            return null;
        }

        return $class;
    }

    /**
     * @param  array<string, mixed>  $fields
     */
    private function isFieldVisibleInForm(
        DocumentTemplateField $field,
        array $fields,
    ): bool {
        if (
            !is_array($field->visibility_rules) ||
            empty($field->visibility_rules)
        ) {
            return true;
        }

        return $this->evaluateVisibilityRules(
            $field->visibility_rules,
            $fields,
        );
    }

    /**
     * @param  array<string, mixed>  $fields
     * @return array<string, mixed>
     */
    private function applyVisibilityRules(
        DocumentTemplate $template,
        array $fields,
    ): array {
        /** @var Collection<int, DocumentTemplateField> $schemaFields */
        $schemaFields = DocumentTemplateField::query()
            ->where('template_id', $template->getKey())
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        foreach ($schemaFields as $schemaField) {
            if (!filled($schemaField->key)) {
                continue;
            }

            if (
                !is_array($schemaField->visibility_rules) ||
                empty($schemaField->visibility_rules)
            ) {
                continue;
            }

            if (
                !$this->evaluateVisibilityRules(
                    $schemaField->visibility_rules,
                    $fields,
                )
            ) {
                unset($fields[$schemaField->key]);
            }
        }

        return $fields;
    }

    /**
     * @param  array<string, mixed>  $rules
     * @param  array<string, mixed>  $fields
     */
    private function evaluateVisibilityRules(array $rules, array $fields): bool
    {
        if (array_key_exists('all', $rules) && is_array($rules['all'])) {
            foreach ($rules['all'] as $rule) {
                if (!is_array($rule)) {
                    continue;
                }

                if (!$this->evaluateVisibilityRule($rule, $fields)) {
                    return false;
                }
            }

            return true;
        }

        if (array_key_exists('any', $rules) && is_array($rules['any'])) {
            foreach ($rules['any'] as $rule) {
                if (!is_array($rule)) {
                    continue;
                }

                if ($this->evaluateVisibilityRule($rule, $fields)) {
                    return true;
                }
            }

            return false;
        }

        return $this->evaluateVisibilityRule($rules, $fields);
    }

    /**
     * @param  array<string, mixed>  $rule
     * @param  array<string, mixed>  $fields
     */
    private function evaluateVisibilityRule(array $rule, array $fields): bool
    {
        $when = (string) ($rule['when'] ?? '');
        if ($when === '') {
            return true;
        }

        $operator = (string) ($rule['operator'] ?? 'filled');
        $expected = $rule['value'] ?? null;
        $actual = $fields[$when] ?? null;

        return match ($operator) {
            'equals' => (string) $actual === (string) $expected,
            'not_equals' => (string) $actual !== (string) $expected,
            'blank' => blank($actual),
            'filled' => filled($actual),
            'in' => is_array($expected)
                ? in_array($actual, $expected, false)
                : false,
            'not_in' => is_array($expected)
                ? !in_array($actual, $expected, false)
                : true,
            default => true,
        };
    }

    /**
     * @param  array<string, mixed>  $fields
     * @return array<string, mixed>
     */
    private function fillDummyData(
        DocumentTemplate $template,
        array $fields,
    ): array {
        /** @var Collection<int, DocumentTemplateField> $schemaFields */
        $schemaFields = DocumentTemplateField::query()
            ->where('template_id', $template->getKey())
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        foreach ($schemaFields as $schemaField) {
            if (!filled($schemaField->key)) {
                continue;
            }

            if (filled($fields[$schemaField->key] ?? null)) {
                continue;
            }

            $fields[$schemaField->key] = match ($schemaField->type) {
                'date' => now()->toDateString(),
                'number' => 1,
                'textarea' => 'Dummy text',
                'select' => 'Dummy',
                'image' => null,
                default => 'Dummy',
            };
        }

        return $fields;
    }

    private function getDefaultValue(DocumentTemplateField $field): mixed
    {
        $value = $field->default_value ?? null;
        if (!is_array($value)) {
            return null;
        }

        if (array_key_exists('value', $value)) {
            return $value['value'];
        }

        return null;
    }
}
