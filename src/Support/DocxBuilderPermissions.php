<?php

namespace Arseno25\DocxBuilder\Support;

class DocxBuilderPermissions
{
    public const TEMPLATES_VIEW_ANY = 'docx-builder.templates.viewAny';
    public const TEMPLATES_CREATE = 'docx-builder.templates.create';
    public const TEMPLATES_UPDATE = 'docx-builder.templates.update';
    public const TEMPLATES_DELETE = 'docx-builder.templates.delete';

    public const CATEGORIES_VIEW_ANY = 'docx-builder.categories.viewAny';
    public const CATEGORIES_CREATE = 'docx-builder.categories.create';
    public const CATEGORIES_UPDATE = 'docx-builder.categories.update';
    public const CATEGORIES_DELETE = 'docx-builder.categories.delete';

    public const VERSIONS_VIEW_ANY = 'docx-builder.versions.viewAny';
    public const VERSIONS_CREATE = 'docx-builder.versions.create';
    public const VERSIONS_UPDATE = 'docx-builder.versions.update';
    public const VERSIONS_DELETE = 'docx-builder.versions.delete';

    public const FIELDS_VIEW_ANY = 'docx-builder.fields.viewAny';
    public const FIELDS_CREATE = 'docx-builder.fields.create';
    public const FIELDS_UPDATE = 'docx-builder.fields.update';
    public const FIELDS_DELETE = 'docx-builder.fields.delete';

    public const PRESETS_VIEW_ANY = 'docx-builder.presets.viewAny';
    public const PRESETS_CREATE = 'docx-builder.presets.create';
    public const PRESETS_UPDATE = 'docx-builder.presets.update';
    public const PRESETS_DELETE = 'docx-builder.presets.delete';

    public const SEQUENCES_VIEW_ANY = 'docx-builder.sequences.viewAny';
    public const SEQUENCES_CREATE = 'docx-builder.sequences.create';
    public const SEQUENCES_UPDATE = 'docx-builder.sequences.update';
    public const SEQUENCES_DELETE = 'docx-builder.sequences.delete';

    public const GENERATIONS_VIEW_ANY = 'docx-builder.generations.viewAny';
    public const GENERATIONS_VIEW = 'docx-builder.generations.view';
    public const GENERATIONS_DOWNLOAD = 'docx-builder.generations.download';
    public const GENERATIONS_RETRY = 'docx-builder.generations.retry';

    public const GENERATE = 'docx-builder.generate';
    public const SETTINGS_VIEW = 'docx-builder.settings.view';
}
