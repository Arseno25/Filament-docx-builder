<?php

use Arseno25\DocxBuilder\Filament\Resources\DocumentTemplates\Pages\CreateDocumentTemplate;
use Arseno25\DocxBuilder\Filament\Resources\DocumentTemplates\DocumentTemplateResource;
use Arseno25\DocxBuilder\Support\DocxBuilderPermissions;
use Livewire\Livewire;

it(
    'denies access to resource pages without the required permissions (PRD 15.12)',
    function () {
        loginWithPermissions([]);

        expect(DocumentTemplateResource::canViewAny())->toBeFalse();
        expect(DocumentTemplateResource::canCreate())->toBeFalse();

        loginWithPermissions([
            DocxBuilderPermissions::TEMPLATES_VIEW_ANY,
            DocxBuilderPermissions::TEMPLATES_CREATE,
        ]);

        expect(DocumentTemplateResource::canViewAny())->toBeTrue();
        expect(DocumentTemplateResource::canCreate())->toBeTrue();

        Livewire::test(CreateDocumentTemplate::class);
    },
);
