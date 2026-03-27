# Filament DOCX Builder

[![Latest Version on Packagist](https://img.shields.io/packagist/v/arseno25/docx-builder.svg?style=flat-square)](https://packagist.org/packages/arseno25/docx-builder)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/arseno25/docx-builder/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/arseno25/docx-builder/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/arseno25/docx-builder/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/arseno25/docx-builder/actions?query=workflow%3A"Fix+PHP+code+styling"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/arseno25/docx-builder.svg?style=flat-square)](https://packagist.org/packages/arseno25/docx-builder)

Generate `.docx` documents from `.docx` templates inside a Filament v5 admin panel.

You upload a Word template, define a field schema (keys, types, validation, defaults, visibility, transforms, data sources), then operators generate documents through a dynamic form or via an optional token-protected API.

> This package is not a full Word editor. Templates are edited in Word and uploaded as `.docx`.

## Installation

You can install the package via composer:

```bash
composer require arseno25/docx-builder
```

### Filament panel setup
Register the plugin in your panel provider:

```php
use Arseno25\DocxBuilder\DocxBuilderPlugin;
use Filament\Panel;

public function panel(Panel $panel): Panel
{
    return $panel
        ->plugins([
            DocxBuilderPlugin::make(),
        ]);
}
```

### Tailwind source (recommended)
If you use a custom Filament theme with Tailwind, add the package views to your Tailwind sources so utility classes are picked up:

```css
@source '../../../../vendor/arseno25/docx-builder/resources/**/*.blade.php';
```

You can publish and run the migrations with:

```bash
php artisan vendor:publish --tag="docx-builder-migrations"
php artisan migrate
```

You can publish the config file with:

```bash
php artisan vendor:publish --tag="docx-builder-config"
```

Optionally, you can publish the views using

```bash
php artisan vendor:publish --tag="docx-builder-views"
```

Optionally, you can publish stubs using:

```bash
php artisan vendor:publish --tag="docx-builder-stubs"
```

## Features

### Template management
- Document templates + categories (Filament resources)
- Template versioning (upload `.docx` versions, set active, rollback)
- Field schema builder (grouping, validation rules, defaults, visibility rules, transforms, data sources)
- Presets (fill empty fields automatically)
- Numbering / sequences (safe preview via peek, real counter increment on final generation)

### Generation workflow
- Test generation mode (optional dummy data)
- Final generation (stores output in configured storage disk)
- Generation history + download action
- Retry failed generation (requires payload snapshots)

### Preview & inspection
- Live preview (text-only extraction from rendered DOCX; realtime with debounce)
- Optional layout preview (PDF) for Word-like rendering (requires LibreOffice headless)
- Payload preview (JSON payload after defaults/presets/source/visibility/transforms)
- Template inspector (shows placeholders found inside a DOCX version and compares to mapped fields)

### Optional API
- Token-protected API routes to generate, fetch status, and download outputs

## Usage (Filament)
After registering the plugin, you get:
- Navigation group **Documents**:
  - **Document Templates** (manage templates, versions, fields, presets, sequences)
  - **Document Generations** (history + download + retry)
  - **Generate Document** (dynamic form + preview)
- Navigation group **Settings**:
  - **Docx Builder Settings** (DB-backed settings that override runtime config)

## Permissions
This package registers Laravel policies and checks permissions using the strings in `Arseno25\DocxBuilder\Support\DocxBuilderPermissions`.

If you use a permissions package like Filament Shield / Spatie Permission, grant the relevant permissions to your roles/users.
See `src/Support/DocxBuilderPermissions.php` for the full list.

## Placeholder syntax
This package uses a small, safe syntax applied to DOCX XML parts:

### Scalars
- `[doc.name]`
- `{{doc.name}}`

### Blocks
- `{{#if doc.is_active}}...{{/if}}`
- `{{#unless doc.is_active}}...{{/unless}}`
- `{{#each doc.items}}...{{/each}}` with inner `{{this}}`, `{{this.name}}`, or `{{name}}` when each item is an array.

### Images
To replace an image, insert a picture in Word and set the picture’s **Title** or **Description** (docPr `title`/`descr`) to:
- `[doc.logo]` or `{{doc.logo}}`

Then provide an image value for `logo` in the payload:
- from upload/preset: `['disk' => 'local', 'path' => '...']`
- or as bytes: `['bytes' => '...']`

## Transform rules
Transform rules are JSON arrays stored per field. Supported step types:
- `trim`, `upper`, `lower`, `title`
- `replace` (`search`, `replace`)
- `prefix` (`value`), `suffix` (`value`)
- `pad_left` (`length`, `pad`)
- `date_format` (`format`, optional `input_format`)
- `number_format` (`decimals`, `decimal_separator`, `thousands_separator`)

Example:
```json
[
  {"type":"trim"},
  {"type":"upper"}
]
```

## Data sources
### Prefill from a source record
If the template has a source model configured, fields with `data_source_type=source_record` (or empty) can be prefilled from `data_source_config.attribute` (defaults to the field key).

### Select options
Select fields support:
- `static_options` (key/value options)
- `enum` (enum class cases)
- `model` (simple model query via `model_class`, `value_attribute`, `label_attribute`, `order_by`, `limit`)

## Settings (DB-backed)
The Settings page persists to the `docx_settings` table and overrides runtime config on boot.

### Layout preview (PDF)
For a Word/PDF-like preview with accurate layout, the package can render the current form payload to a DOCX and convert it to a PDF preview.

Requirements:
- LibreOffice installed on the server (headless conversion via `soffice`)

Enable via `.env`:
```env
DOCX_BUILDER_LAYOUT_PREVIEW_ENABLED=true
DOCX_BUILDER_LAYOUT_PREVIEW_ENABLED_BY_DEFAULT=true
DOCX_BUILDER_LAYOUT_PREVIEW_SOFFICE=soffice
DOCX_BUILDER_LAYOUT_PREVIEW_TTL_MINUTES=10
```

Notes:
- The preview URL is signed and also requires an authenticated user with the `docx-builder.generate` permission.
- PDF files are stored on the configured disk (`DOCX_BUILDER_LAYOUT_PREVIEW_DISK`, defaults to the output disk) under `DOCX_BUILDER_LAYOUT_PREVIEW_PATH_PREFIX`.

## CLI
Run an installation report:

```bash
php artisan docx-builder
php artisan docx-builder --json
```

## API (optional)
Enable the API in `.env`:

```env
DOCX_BUILDER_API_ENABLED=true
DOCX_BUILDER_API_TOKEN=your-secret-token
DOCX_BUILDER_API_PREFIX=docx-builder
```

Authentication:
- Send `X-Docx-Builder-Token: <token>` header, or a Bearer token.

Endpoints:
- `POST /{prefix}/generations` (returns 201 success, 202 queued)
- `GET /{prefix}/generations/{id}`
- `GET /{prefix}/generations/{id}/download`

## Configuration
The published config file lives at `config/docx-builder.php` and includes:
- storage disks + output path prefix
- retention policies
- payload snapshot policy (needed for retry)
- queue options
- preview options (text-only preview + optional PDF layout preview)
- API options

```php
return [
    // See config/docx-builder.php
];
```

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](.github/CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](.github/SECURITY.md) on how to report security vulnerabilities.

## Credits

- [Arseno25](https://github.com/Arseno25)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
