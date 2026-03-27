# This is my package docx-builder

[![Latest Version on Packagist](https://img.shields.io/packagist/v/arseno25/docx-builder.svg?style=flat-square)](https://packagist.org/packages/arseno25/docx-builder)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/arseno25/docx-builder/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/arseno25/docx-builder/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/arseno25/docx-builder/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/arseno25/docx-builder/actions?query=workflow%3A"Fix+PHP+code+styling"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/arseno25/docx-builder.svg?style=flat-square)](https://packagist.org/packages/arseno25/docx-builder)



This is where your description should go. Limit it to a paragraph or two. Consider adding a small example.

## Installation

You can install the package via composer:

```bash
composer require arseno25/docx-builder
```

> [!IMPORTANT]
> If you have not set up a custom theme and are using Filament Panels follow the instructions in the [Filament Docs](https://filamentphp.com/docs/4.x/styling/overview#creating-a-custom-theme) first.

After setting up a custom theme add the plugin's views to your theme css file or your app's css file if using the standalone packages.

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

This is the contents of the published config file:

```php
return [
];
```

## Usage

```php
$docxBuilder = new Arseno25\DocxBuilder();
echo $docxBuilder->echoPhrase('Hello, Arseno25!');
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
