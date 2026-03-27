<?php

namespace Arseno25\DocxBuilder;

use Arseno25\DocxBuilder\Filament\Pages\DocxBuilderSettings;
use Arseno25\DocxBuilder\Filament\Pages\GenerateDocument;
use Arseno25\DocxBuilder\Filament\Resources\DocumentGenerations\DocumentGenerationResource;
use Arseno25\DocxBuilder\Filament\Resources\DocumentTemplateCategories\DocumentTemplateCategoryResource;
use Arseno25\DocxBuilder\Filament\Resources\DocumentTemplates\DocumentTemplateResource;
use Filament\Contracts\Plugin;
use Filament\Panel;

class DocxBuilderPlugin implements Plugin
{
    public function getId(): string
    {
        return 'docx-builder';
    }

    public function register(Panel $panel): void
    {
        $panel
            ->resources([
                DocumentTemplateResource::class,
                DocumentTemplateCategoryResource::class,
                DocumentGenerationResource::class,
            ])
            ->pages([GenerateDocument::class, DocxBuilderSettings::class]);
    }

    public function boot(Panel $panel): void
    {
        //
    }

    public static function make(): static
    {
        return app(static::class);
    }

    public static function get(): static
    {
        /** @var static $plugin */
        $plugin = filament(app(static::class)->getId());

        return $plugin;
    }
}
