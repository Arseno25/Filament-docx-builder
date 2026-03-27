<?php

namespace Arseno25\DocxBuilder;

use Arseno25\DocxBuilder\Commands\DocxBuilderCommand;
use Arseno25\DocxBuilder\Testing\TestsDocxBuilder;
use Filament\Support\Assets\AlpineComponent;
use Filament\Support\Assets\Asset;
use Filament\Support\Assets\Css;
use Filament\Support\Assets\Js;
use Filament\Support\Facades\FilamentAsset;
use Filament\Support\Facades\FilamentIcon;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Gate;
use Livewire\Features\SupportTesting\Testable;
use Spatie\LaravelPackageTools\Commands\InstallCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Arseno25\DocxBuilder\Commands\DocxBuilderCommand;
use Arseno25\DocxBuilder\Commands\CleanupDocxGenerationsCommand;
use Arseno25\DocxBuilder\Models\DocumentGeneration;
use Arseno25\DocxBuilder\Models\DocumentNumberSequence;
use Arseno25\DocxBuilder\Models\DocumentPreset;
use Arseno25\DocxBuilder\Models\DocumentTemplate;
use Arseno25\DocxBuilder\Models\DocumentTemplateCategory;
use Arseno25\DocxBuilder\Models\DocumentTemplateField;
use Arseno25\DocxBuilder\Models\DocumentTemplateVersion;
use Arseno25\DocxBuilder\Policies\DocumentGenerationPolicy;
use Arseno25\DocxBuilder\Policies\DocumentNumberSequencePolicy;
use Arseno25\DocxBuilder\Policies\DocumentPresetPolicy;
use Arseno25\DocxBuilder\Policies\DocumentTemplateCategoryPolicy;
use Arseno25\DocxBuilder\Policies\DocumentTemplateFieldPolicy;
use Arseno25\DocxBuilder\Policies\DocumentTemplatePolicy;
use Arseno25\DocxBuilder\Policies\DocumentTemplateVersionPolicy;
use Arseno25\DocxBuilder\Contracts\DocxToPdfConverterInterface;
use Arseno25\DocxBuilder\Converters\LibreOfficeDocxToPdfConverter;
use Arseno25\DocxBuilder\Rendering\OpenTbsRenderer;
use Arseno25\DocxBuilder\Rendering\RendererInterface;
use Arseno25\DocxBuilder\Services\DocxSettingsService;
use Arseno25\DocxBuilder\Testing\TestsDocxBuilder;

class DocxBuilderServiceProvider extends PackageServiceProvider
{
    public static string $name = 'docx-builder';

    public static string $viewNamespace = 'docx-builder';

    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name(static::$name)
            ->hasCommands($this->getCommands())
            ->hasRoutes('api', 'web')
            ->hasInstallCommand(function (InstallCommand $command) {
                $command
                    ->publishConfigFile()
                    ->publishMigrations()
                    ->askToRunMigrations()
                    ->askToStarRepoOnGitHub('arseno25/docx-builder');
            });

        $configFileName = $package->shortName();

        if (
            file_exists($package->basePath("/../config/{$configFileName}.php"))
        ) {
            $package->hasConfigFile();
        }

        if (file_exists($package->basePath('/../database/migrations'))) {
            $package->hasMigrations($this->getMigrations());
        }

        if (file_exists($package->basePath('/../resources/lang'))) {
            $package->hasTranslations();
        }

        if (file_exists($package->basePath('/../resources/views'))) {
            $package->hasViews(static::$viewNamespace);
        }
    }

    public function packageRegistered(): void
    {
        $this->app->bind(RendererInterface::class, OpenTbsRenderer::class);
        $this->app->bind(
            DocxToPdfConverterInterface::class,
            LibreOfficeDocxToPdfConverter::class,
        );
    }

    public function packageBooted(): void
    {
        /** @var DocxSettingsService $settings */
        $settings = app(DocxSettingsService::class);
        if ($settings->tableExists()) {
            $settings->applyToConfig($settings->get());
        }

        Gate::policy(DocumentTemplate::class, DocumentTemplatePolicy::class);
        Gate::policy(
            DocumentTemplateCategory::class,
            DocumentTemplateCategoryPolicy::class,
        );
        Gate::policy(
            DocumentTemplateVersion::class,
            DocumentTemplateVersionPolicy::class,
        );
        Gate::policy(
            DocumentTemplateField::class,
            DocumentTemplateFieldPolicy::class,
        );
        Gate::policy(DocumentPreset::class, DocumentPresetPolicy::class);
        Gate::policy(
            DocumentNumberSequence::class,
            DocumentNumberSequencePolicy::class,
        );
        Gate::policy(
            DocumentGeneration::class,
            DocumentGenerationPolicy::class,
        );

        // Asset Registration
        FilamentAsset::register(
            $this->getAssets(),
            $this->getAssetPackageName(),
        );

        FilamentAsset::registerScriptData(
            $this->getScriptData(),
            $this->getAssetPackageName(),
        );

        // Icon Registration
        FilamentIcon::register($this->getIcons());

        // Handle Stubs
        if (app()->runningInConsole()) {
            foreach (
                app(Filesystem::class)->files(__DIR__ . '/../stubs/')
                as $file
            ) {
                $this->publishes(
                    [
                        $file->getRealPath() => base_path(
                            "stubs/docx-builder/{$file->getFilename()}",
                        ),
                    ],
                    'docx-builder-stubs',
                );
            }
        }

        // Testing
        Testable::mixin(new TestsDocxBuilder());
    }

    protected function getAssetPackageName(): ?string
    {
        return 'arseno25/docx-builder';
    }

    /**
     * @return array<Asset>
     */
    protected function getAssets(): array
    {
        return [
                // AlpineComponent::make('docx-builder', __DIR__ . '/../resources/dist/components/docx-builder.js'),
                // Css::make('docx-builder-styles', __DIR__ . '/../resources/dist/docx-builder.css'),
                // Js::make('docx-builder-scripts', __DIR__ . '/../resources/dist/docx-builder.js'),
            ];
    }

    /**
     * @return array<class-string>
     */
    protected function getCommands(): array
    {
        return [
            CleanupDocxGenerationsCommand::class,
            DocxBuilderCommand::class,
        ];
    }

    /**
     * @return array<string>
     */
    protected function getIcons(): array
    {
        return [];
    }

    /**
     * @return array<string>
     */
    protected function getRoutes(): array
    {
        return [];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getScriptData(): array
    {
        return [];
    }

    /**
     * @return array<string>
     */
    protected function getMigrations(): array
    {
        return [
            'create_docx_template_categories_table',
            'create_docx_templates_table',
            'create_docx_template_versions_table',
            'create_docx_template_fields_table',
            'create_docx_presets_table',
            'create_docx_number_sequences_table',
            'create_docx_generations_table',
            'add_docx_template_model_binding_columns',
            'create_docx_settings_table',
        ];
    }
}
