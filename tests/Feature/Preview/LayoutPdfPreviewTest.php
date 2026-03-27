<?php

use Arseno25\DocxBuilder\Contracts\DocxToPdfConverterInterface;
use Arseno25\DocxBuilder\Models\DocumentTemplate;
use Arseno25\DocxBuilder\Models\DocumentTemplateVersion;
use Arseno25\DocxBuilder\Services\DocxLayoutPreviewService;
use Arseno25\DocxBuilder\Support\DocxBuilderPermissions;
use Arseno25\DocxBuilder\Tests\Support\DocxFixtureFactory;
use Arseno25\DocxBuilder\Tests\Support\FakeDocxToPdfConverter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

beforeEach(function () {
    Carbon::setTestNow(Carbon::create(2026, 3, 27, 10, 0, 0));

    Storage::fake('local');
    config()->set('docx-builder.template_disk', 'local');
    config()->set('docx-builder.output_disk', 'local');
    config()->set('docx-builder.output_path_prefix', 'docx-builder');

    config()->set('docx-builder.preview.layout.enabled', true);
    config()->set('docx-builder.preview.layout.enabled_by_default', true);
    config()->set('docx-builder.preview.layout.disk', 'local');
    config()->set(
        'docx-builder.preview.layout.path_prefix',
        'docx-builder/previews',
    );
    config()->set('docx-builder.preview.layout.ttl_minutes', 10);

    app()->bind(
        DocxToPdfConverterInterface::class,
        FakeDocxToPdfConverter::class,
    );
});

afterEach(function () {
    Carbon::setTestNow();
});

it('streams a signed PDF preview for an authorized user', function () {
    loginWithPermissions([DocxBuilderPermissions::GENERATE]);

    $docx = DocxFixtureFactory::minimalTextDocx(
        '<w:p><w:r><w:t>Hello {{doc.name}}</w:t></w:r></w:p>',
    );
    Storage::disk('local')->put('templates/source.docx', $docx);

    $template = DocumentTemplate::create([
        'code' => 'TMP-PDF1',
        'name' => 'Template',
        'status' => 'draft',
        'visibility' => 'internal',
        'output_filename_pattern' => 'TEST_{doc.name}',
    ]);

    $version = DocumentTemplateVersion::create([
        'template_id' => $template->id,
        'version' => 'v1',
        'is_active' => true,
        'source_disk' => 'local',
        'source_path' => 'templates/source.docx',
    ]);

    $svc = app(DocxLayoutPreviewService::class);
    $url = $svc->previewPdfUrl($version, ['doc' => ['name' => 'John']]);

    $resp = $this->get($url);
    $resp->assertOk();
    $resp->assertHeader('content-type', 'application/pdf');
    expect((string) $resp->streamedContent())->toStartWith('%PDF-1.4');
});

it('returns 403 when user is not authenticated', function () {
    $key = 'abc';
    Cache::put(
        "docx-builder:layout-preview:{$key}",
        ['disk' => 'local', 'path' => 'docx-builder/previews/a.pdf'],
        now()->addMinutes(10),
    );
    Storage::disk('local')->put('docx-builder/previews/a.pdf', "%PDF-1.4\n");

    $url = URL::temporarySignedRoute(
        'docx-builder.preview.pdf',
        now()->addMinutes(10),
        ['key' => $key],
    );

    $this->get($url)->assertForbidden();
});

it('returns 403 when user lacks permission', function () {
    loginWithPermissions([]);

    $key = 'no-perm';
    Cache::put(
        "docx-builder:layout-preview:{$key}",
        ['disk' => 'local', 'path' => 'docx-builder/previews/b.pdf'],
        now()->addMinutes(10),
    );
    Storage::disk('local')->put('docx-builder/previews/b.pdf', "%PDF-1.4\n");

    $url = URL::temporarySignedRoute(
        'docx-builder.preview.pdf',
        now()->addMinutes(10),
        ['key' => $key],
    );

    $this->get($url)->assertForbidden();
});

it(
    'returns 404 when layout preview is disabled (even with a valid signature)',
    function () {
        loginWithPermissions([DocxBuilderPermissions::GENERATE]);

        $key = 'disabled';
        Cache::put(
            "docx-builder:layout-preview:{$key}",
            ['disk' => 'local', 'path' => 'docx-builder/previews/c.pdf'],
            now()->addMinutes(10),
        );
        Storage::disk('local')->put(
            'docx-builder/previews/c.pdf',
            "%PDF-1.4\n",
        );

        $url = URL::temporarySignedRoute(
            'docx-builder.preview.pdf',
            now()->addMinutes(10),
            ['key' => $key],
        );

        config()->set('docx-builder.preview.layout.enabled', false);

        $this->get($url)->assertNotFound();
    },
);

it('rejects requests without a signature', function () {
    loginWithPermissions([DocxBuilderPermissions::GENERATE]);

    $this->get('/docx-builder/previews/unsigned.pdf')->assertForbidden();
});

it(
    'returns 404 when preview metadata is missing (with a valid signature)',
    function () {
        loginWithPermissions([DocxBuilderPermissions::GENERATE]);

        $key = 'missing-meta';

        $url = URL::temporarySignedRoute(
            'docx-builder.preview.pdf',
            now()->addMinutes(10),
            ['key' => $key],
        );

        $this->get($url)->assertNotFound();
    },
);
