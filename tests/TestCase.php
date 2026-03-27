<?php

namespace Arseno25\DocxBuilder\Tests;

use BladeUI\Heroicons\BladeHeroiconsServiceProvider;
use BladeUI\Icons\BladeIconsServiceProvider;
use Filament\Actions\ActionsServiceProvider;
use Filament\FilamentServiceProvider;
use Filament\Forms\FormsServiceProvider;
use Filament\Infolists\InfolistsServiceProvider;
use Filament\Notifications\NotificationsServiceProvider;
use Filament\Schemas\SchemasServiceProvider;
use Filament\Support\SupportServiceProvider;
use Filament\Tables\TablesServiceProvider;
use Filament\Widgets\WidgetsServiceProvider;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Filesystem\Filesystem;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\Concerns\WithWorkbench;
use Orchestra\Testbench\TestCase as Orchestra;
use Arseno25\DocxBuilder\DocxBuilderServiceProvider;
use Arseno25\DocxBuilder\Tests\Support\TestPanelProvider;
use Arseno25\DocxBuilder\Tests\Support\Models\TestUser;

class TestCase extends Orchestra
{
    use LazilyRefreshDatabase;
    use WithWorkbench;

    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn(
                string $modelName,
            ) => 'Arseno25\\DocxBuilder\\Database\\Factories\\' .
                class_basename($modelName) .
                'Factory',
        );
    }

    protected function getPackageProviders($app)
    {
        $providers = [
            ActionsServiceProvider::class,
            BladeHeroiconsServiceProvider::class,
            BladeIconsServiceProvider::class,
            FilamentServiceProvider::class,
            FormsServiceProvider::class,
            InfolistsServiceProvider::class,
            LivewireServiceProvider::class,
            NotificationsServiceProvider::class,
            SchemasServiceProvider::class,
            SupportServiceProvider::class,
            TablesServiceProvider::class,
            WidgetsServiceProvider::class,
            DocxBuilderServiceProvider::class,
            TestPanelProvider::class,
        ];

        sort($providers);

        return $providers;
    }

    public function getEnvironmentSetUp($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set(
            'app.key',
            'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=',
        );
        $app['config']->set('app.cipher', 'AES-256-CBC');
        $app['config']->set('auth.providers.users.model', TestUser::class);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom($this->preparePackageMigrationStubs());
    }

    protected function preparePackageMigrationStubs(): string
    {
        $filesystem = new Filesystem();

        $tempPath = __DIR__ . '/../build/testbench-migrations';
        $filesystem->ensureDirectoryExists($tempPath);

        $stubs = [
            'create_docx_template_categories_table.php.stub',
            'create_docx_templates_table.php.stub',
            'create_docx_template_versions_table.php.stub',
            'create_docx_template_fields_table.php.stub',
            'create_docx_presets_table.php.stub',
            'create_docx_number_sequences_table.php.stub',
            'create_docx_generations_table.php.stub',
            'add_docx_template_model_binding_columns.php.stub',
            'create_docx_settings_table.php.stub',
        ];

        foreach ($stubs as $index => $stubFile) {
            $stubPath = __DIR__ . '/../database/migrations/' . $stubFile;

            if (!$filesystem->exists($stubPath)) {
                continue;
            }

            $timestamp = sprintf('2026_01_01_%06d', $index + 1);
            $name = str_replace('.php.stub', '.php', $stubFile);
            $targetPath = $tempPath . '/' . $timestamp . '_' . $name;

            $filesystem->copy($stubPath, $targetPath);
        }

        $extra = [
            'create_users_table.php' => <<<'PHP'
            <?php

            use Illuminate\Database\Migrations\Migration;
            use Illuminate\Database\Schema\Blueprint;
            use Illuminate\Support\Facades\Schema;

            return new class extends Migration
            {
                public function up(): void
                {
                    if (Schema::hasTable('users')) {
                        if (! Schema::hasColumn('users', 'permissions')) {
                            Schema::table('users', function (Blueprint $table) {
                                $table->json('permissions')->nullable();
                            });
                        }

                        return;
                    }

                    Schema::create('users', function (Blueprint $table) {
                        $table->id();
                        $table->string('name');
                        $table->string('email')->nullable()->unique();
                        $table->timestamp('email_verified_at')->nullable();
                        $table->string('password')->nullable();
                        $table->json('permissions')->nullable();
                        $table->rememberToken();
                        $table->timestamps();
                    });
                }
            };
            PHP
            ,
            'create_test_people_table.php' => <<<'PHP'
            <?php

            use Illuminate\Database\Migrations\Migration;
            use Illuminate\Database\Schema\Blueprint;
            use Illuminate\Support\Facades\Schema;

            return new class extends Migration
            {
                public function up(): void
                {
                    if (Schema::hasTable('test_people')) {
                        return;
                    }

                    Schema::create('test_people', function (Blueprint $table) {
                        $table->id();
                        $table->string('name');
                        $table->timestamps();
                    });
                }
            };
            PHP
        ,
        ];

        $offset = count($stubs);
        $extraIndex = 0;

        foreach ($extra as $fileName => $contents) {
            $timestamp = sprintf('2026_01_01_%06d', $offset + ++$extraIndex);

            $filesystem->put(
                $tempPath . '/' . $timestamp . '_' . $fileName,
                $contents,
            );
        }

        return $tempPath;
    }
}
