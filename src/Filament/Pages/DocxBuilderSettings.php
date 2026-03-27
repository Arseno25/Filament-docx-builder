<?php

namespace Arseno25\DocxBuilder\Filament\Pages;

use Arseno25\DocxBuilder\Services\DocxSettingsService;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Arseno25\DocxBuilder\Support\DocxBuilderPermissions;
use UnitEnum;

class DocxBuilderSettings extends Page implements HasSchemas
{
    use InteractsWithSchemas;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected string $view = 'docx-builder::filament.pages.settings';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        $user = auth()->user();

        if (!$user) {
            return false;
        }

        if (method_exists($user, 'hasPermissionTo')) {
            return (bool) $user->hasPermissionTo(
                DocxBuilderPermissions::SETTINGS_VIEW,
            );
        }

        return (bool) $user->can(DocxBuilderPermissions::SETTINGS_VIEW);
    }

    public function mount(): void
    {
        /** @var DocxSettingsService $service */
        $service = app(DocxSettingsService::class);

        $setting = $service->get();

        $retention = (array) config('docx-builder.retention_days', []);

        $this->form->fill([
            'template_disk' =>
                $setting?->template_disk ?:
                (string) config('docx-builder.template_disk', 'local'),
            'output_disk' =>
                $setting?->output_disk ?:
                (string) config('docx-builder.output_disk', 'local'),
            'output_path_prefix' =>
                $setting?->output_path_prefix ?:
                (string) config(
                    'docx-builder.output_path_prefix',
                    'docx-builder',
                ),
            'retention_test_days' =>
                $setting?->retention_test_days ?? ($retention['test'] ?? 7),
            'retention_final_days' =>
                $setting?->retention_final_days ??
                ($retention['final'] ?? null),
            'payload_snapshot_policy' =>
                $setting?->payload_snapshot_policy ?:
                (string) config('docx-builder.payload_snapshot_policy', 'off'),
            'queue_enabled' =>
                $setting?->queue_enabled ??
                (bool) config('docx-builder.queue.enabled', false),
            'queue_connection' =>
                $setting?->queue_connection ??
                config('docx-builder.queue.connection'),
            'queue_queue' =>
                $setting?->queue_queue ?? config('docx-builder.queue.queue'),
            'preview_enabled_by_default' =>
                $setting?->preview_enabled_by_default ??
                (bool) config('docx-builder.preview.enabled_by_default', true),
            'preview_max_chars' =>
                $setting?->preview_max_chars ??
                (int) config('docx-builder.preview.max_chars', 12000),
            'preview_debounce_ms' =>
                $setting?->preview_debounce_ms ??
                (int) config('docx-builder.preview.debounce_ms', 700),
            'layout_preview_enabled' =>
                $setting?->layout_preview_enabled ??
                (bool) config('docx-builder.preview.layout.enabled', false),
            'layout_preview_enabled_by_default' =>
                $setting?->layout_preview_enabled_by_default ??
                (bool) config(
                    'docx-builder.preview.layout.enabled_by_default',
                    false,
                ),
            'layout_preview_driver' =>
                $setting?->layout_preview_driver ??
                (string) config(
                    'docx-builder.preview.layout.driver',
                    'libreoffice',
                ),
            'layout_preview_soffice_binary' =>
                $setting?->layout_preview_soffice_binary ??
                (string) config(
                    'docx-builder.preview.layout.soffice_binary',
                    'soffice',
                ),
            'layout_preview_disk' =>
                $setting?->layout_preview_disk ??
                (string) (config('docx-builder.preview.layout.disk') ?? ''),
            'layout_preview_path_prefix' =>
                $setting?->layout_preview_path_prefix ??
                (string) config(
                    'docx-builder.preview.layout.path_prefix',
                    'docx-builder/previews',
                ),
            'layout_preview_ttl_minutes' =>
                $setting?->layout_preview_ttl_minutes ??
                (int) config('docx-builder.preview.layout.ttl_minutes', 10),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Storage')
                    ->schema([
                        TextInput::make('template_disk')
                            ->label('Template disk')
                            ->helperText(
                                'Storage disk used to store uploaded DOCX templates.',
                            )
                            ->required(),
                        TextInput::make('output_disk')
                            ->label('Output disk')
                            ->helperText(
                                'Storage disk used to store generated documents.',
                            )
                            ->required(),
                        TextInput::make('output_path_prefix')
                            ->label('Output path prefix')
                            ->helperText(
                                'Prefix directory inside the output disk.',
                            )
                            ->required(),
                    ])
                    ->columns(2)
                    ->compact()
                    ->contained(),
                Section::make('Retention & snapshots')
                    ->schema([
                        TextInput::make('retention_test_days')
                            ->label('Retention for test generations (days)')
                            ->numeric()
                            ->minValue(0)
                            ->required(),
                        TextInput::make('retention_final_days')
                            ->label('Retention for final generations (days)')
                            ->numeric()
                            ->minValue(0)
                            ->helperText(
                                'Leave empty to keep final documents indefinitely.',
                            ),
                        Select::make('payload_snapshot_policy')
                            ->label('Payload snapshot policy')
                            ->options([
                                'off' => 'Off',
                                'on_success' => 'On success',
                                'always' => 'Always',
                            ])
                            ->required(),
                    ])
                    ->columns(2)
                    ->compact()
                    ->contained(),
                Section::make('Queue')
                    ->schema([
                        Toggle::make('queue_enabled')
                            ->label('Enable queued rendering')
                            ->helperText(
                                'When enabled, final generation can be queued using the configured queue connection.',
                            )
                            ->live(),
                        TextInput::make('queue_connection')
                            ->label('Queue connection')
                            ->visible(
                                fn(Get $get): bool => (bool) $get(
                                    'queue_enabled',
                                ),
                            ),
                        TextInput::make('queue_queue')
                            ->label('Queue name')
                            ->visible(
                                fn(Get $get): bool => (bool) $get(
                                    'queue_enabled',
                                ),
                            ),
                    ])
                    ->columns(2)
                    ->compact()
                    ->contained(),
                Section::make('Preview')
                    ->schema([
                        Toggle::make('preview_enabled_by_default')
                            ->label('Enable live preview by default')
                            ->required(),
                        TextInput::make('preview_max_chars')
                            ->label('Preview max characters')
                            ->numeric()
                            ->minValue(1000)
                            ->required(),
                        TextInput::make('preview_debounce_ms')
                            ->label('Preview debounce (ms)')
                            ->numeric()
                            ->minValue(100)
                            ->required(),
                    ])
                    ->columns(2)
                    ->compact()
                    ->contained(),
                Section::make('Layout preview (PDF)')
                    ->schema([
                        Toggle::make('layout_preview_enabled')
                            ->label('Enable layout preview')
                            ->helperText(
                                'Converts rendered DOCX to PDF (Word-like layout). Requires LibreOffice on the server.',
                            )
                            ->live(),
                        Toggle::make('layout_preview_enabled_by_default')
                            ->label('Enable by default')
                            ->visible(
                                fn(Get $get): bool => (bool) $get(
                                    'layout_preview_enabled',
                                ),
                            ),
                        Select::make('layout_preview_driver')
                            ->label('Driver')
                            ->options([
                                'libreoffice' => 'LibreOffice (headless)',
                            ])
                            ->required()
                            ->visible(
                                fn(Get $get): bool => (bool) $get(
                                    'layout_preview_enabled',
                                ),
                            ),
                        TextInput::make('layout_preview_soffice_binary')
                            ->label('LibreOffice binary')
                            ->helperText(
                                'Example (Windows): C:\\Program Files\\LibreOffice\\program\\soffice.exe',
                            )
                            ->required()
                            ->visible(
                                fn(Get $get): bool => (bool) $get(
                                    'layout_preview_enabled',
                                ),
                            ),
                        TextInput::make('layout_preview_disk')
                            ->label('Preview disk (optional)')
                            ->helperText('Leave empty to use the output disk.')
                            ->visible(
                                fn(Get $get): bool => (bool) $get(
                                    'layout_preview_enabled',
                                ),
                            ),
                        TextInput::make('layout_preview_path_prefix')
                            ->label('Preview path prefix')
                            ->required()
                            ->visible(
                                fn(Get $get): bool => (bool) $get(
                                    'layout_preview_enabled',
                                ),
                            ),
                        TextInput::make('layout_preview_ttl_minutes')
                            ->label('Preview TTL (minutes)')
                            ->numeric()
                            ->minValue(1)
                            ->required()
                            ->visible(
                                fn(Get $get): bool => (bool) $get(
                                    'layout_preview_enabled',
                                ),
                            ),
                    ])
                    ->columns(2)
                    ->compact()
                    ->contained(),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        /** @var array<string, mixed> $state */
        $state = $this->form->getState();

        $data = $this->normalizeDataForPersistence($state);

        /** @var DocxSettingsService $service */
        $service = app(DocxSettingsService::class);

        $setting = $service->save($data, auth()->id());
        $service->applyToConfig($setting);

        Notification::make()->title('Settings saved.')->success()->send();
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    private function normalizeDataForPersistence(array $state): array
    {
        $retentionFinal = $state['retention_final_days'] ?? null;

        return [
            'template_disk' => (string) ($state['template_disk'] ?? 'local'),
            'output_disk' => (string) ($state['output_disk'] ?? 'local'),
            'output_path_prefix' =>
                (string) ($state['output_path_prefix'] ?? 'docx-builder'),
            'retention_test_days' => (int) ($state['retention_test_days'] ?? 7),
            'retention_final_days' => filled($retentionFinal)
                ? (int) $retentionFinal
                : null,
            'payload_snapshot_policy' =>
                (string) ($state['payload_snapshot_policy'] ?? 'off'),
            'queue_enabled' => (bool) ($state['queue_enabled'] ?? false),
            'queue_connection' => filled($state['queue_connection'] ?? null)
                ? (string) $state['queue_connection']
                : null,
            'queue_queue' => filled($state['queue_queue'] ?? null)
                ? (string) $state['queue_queue']
                : null,
            'preview_enabled_by_default' =>
                (bool) ($state['preview_enabled_by_default'] ?? true),
            'preview_max_chars' => (int) ($state['preview_max_chars'] ?? 12000),
            'preview_debounce_ms' =>
                (int) ($state['preview_debounce_ms'] ?? 700),
            'layout_preview_enabled' =>
                (bool) ($state['layout_preview_enabled'] ?? false),
            'layout_preview_enabled_by_default' =>
                (bool) ($state['layout_preview_enabled_by_default'] ?? false),
            'layout_preview_driver' => filled(
                $state['layout_preview_driver'] ?? null,
            )
                ? (string) $state['layout_preview_driver']
                : null,
            'layout_preview_soffice_binary' => filled(
                $state['layout_preview_soffice_binary'] ?? null,
            )
                ? (string) $state['layout_preview_soffice_binary']
                : null,
            'layout_preview_disk' => filled(
                $state['layout_preview_disk'] ?? null,
            )
                ? (string) $state['layout_preview_disk']
                : null,
            'layout_preview_path_prefix' => filled(
                $state['layout_preview_path_prefix'] ?? null,
            )
                ? (string) $state['layout_preview_path_prefix']
                : null,
            'layout_preview_ttl_minutes' =>
                (int) ($state['layout_preview_ttl_minutes'] ?? 10),
        ];
    }
}
