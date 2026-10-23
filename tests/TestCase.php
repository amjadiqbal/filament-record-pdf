<?php

declare(strict_types=1);

namespace AmjadIqbal\FilamentRecordPdf\Tests;

use AmjadIqbal\FilamentRecordPdf\FilamentRecordPdfServiceProvider;
use AmjadIqbal\FilamentRecordPdf\Tests\Fixtures\AdminPanelProvider;
use AmjadIqbal\FilamentRecordPdf\Tests\Fixtures\Invoice;
use AmjadIqbal\FilamentRecordPdf\Tests\Fixtures\InvoicePolicy;
use AmjadIqbal\FilamentRecordPdf\Tests\Fixtures\InvoiceResource;
use AmjadIqbal\FilamentRecordPdf\Tests\Fixtures\User;
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
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Kirschbaum\PowerJoins\PowerJoinsServiceProvider;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use RyanChandler\BladeCaptureDirective\BladeCaptureDirectiveServiceProvider;
use Spatie\LaravelPdf\PdfServiceProvider;
use Spatie\MediaLibrary\MediaLibraryServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        InvoicePolicy::$deny = [];
        InvoiceResource::$configure = [];
        Gate::policy(Invoice::class, InvoicePolicy::class);
    }

    protected function getPackageProviders($app): array
    {
        $providers = [
            BladeIconsServiceProvider::class,
            BladeHeroiconsServiceProvider::class,
            BladeCaptureDirectiveServiceProvider::class,
            PowerJoinsServiceProvider::class,
            LivewireServiceProvider::class,
            SupportServiceProvider::class,
            ActionsServiceProvider::class,
            FormsServiceProvider::class,
            InfolistsServiceProvider::class,
            NotificationsServiceProvider::class,
            'Filament\\QueryBuilder\\QueryBuilderServiceProvider',
            SchemasServiceProvider::class,
            TablesServiceProvider::class,
            WidgetsServiceProvider::class,
            FilamentServiceProvider::class,
            PdfServiceProvider::class,
            MediaLibraryServiceProvider::class,
            FilamentRecordPdfServiceProvider::class,
            AdminPanelProvider::class,
        ];

        return array_values(array_filter($providers, 'class_exists'));
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
        $app['config']->set('database.default', 'testing');
        $app['config']->set('queue.default', 'sync');
        $app['config']->set('cache.default', 'array');
        $app['config']->set('auth.providers.users.model', User::class);
        $app['config']->set('filesystems.disks.public', ['driver' => 'local', 'root' => sys_get_temp_dir().'/record-pdf-public-'.getmypid()]);
        // Prove the plugin does not depend on laravel-pdf's own default (Browsershot, which needs Chromium).
        $app['config']->set('laravel-pdf.driver', 'browsershot');
    }

    protected function defineDatabaseMigrations(): void
    {
        Schema::dropAllTables();

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('invoices', function (Blueprint $table): void {
            $table->id();
            $table->string('number');
            $table->string('customer');
            $table->string('email')->nullable();
            $table->decimal('total', 10, 2)->default(0);
            $table->boolean('paid')->default(false);
            $table->string('secret_note')->nullable();
            $table->string('pdf_path')->nullable();
            $table->timestamps();
        });

        Schema::create('notifications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        $stub = __DIR__.'/../vendor/spatie/laravel-medialibrary/database/migrations/create_media_table.php.stub';
        if (is_file($stub)) {
            (include $stub)->up();
        }
    }

    protected function makeUser(string $name = 'Admin'): User
    {
        return User::query()->create([
            'name' => $name,
            'email' => strtolower($name).'@example.test',
            'password' => 'secret-password',
        ]);
    }

    protected function makeInvoice(array $attributes = []): Invoice
    {
        static $n = 1000;
        $n++;

        return Invoice::query()->create(array_merge([
            'number' => "INV-{$n}",
            'customer' => 'Jane Doe',
            'email' => 'jane@example.test',
            'total' => 150.5,
            'secret_note' => 'do-not-print',
        ], $attributes));
    }
}
