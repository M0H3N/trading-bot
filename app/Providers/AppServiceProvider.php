<?php

namespace App\Providers;

use App\Support\HtmlSanitizer\LegacyDomHtmlParser;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerHttpLogDatabaseConnection();
        $this->registerFilamentHtmlSanitizerFallback();
    }

    /**
     * Filament (symfony/html-sanitizer v8) defaults to NativeParser, which requires PHP 8.4's
     * Dom\HTMLDocument. Re-bind with a DOMDocument-based parser when that class is missing.
     */
    protected function registerFilamentHtmlSanitizerFallback(): void
    {
        if (! interface_exists(HtmlSanitizerInterface::class)) {
            return;
        }

        $this->app->scoped(HtmlSanitizerInterface::class, function (Application $app): HtmlSanitizer {
            $config = $app->make(HtmlSanitizerConfig::class);
            $parser = class_exists(\Dom\HTMLDocument::class)
                ? null
                : new LegacyDomHtmlParser;

            return new HtmlSanitizer($config, $parser);
        });
    }

    /**
     * Duplicate the default connection as `http_logs` so inserts are not rolled back
     * when exchange HTTP runs inside an application DB::transaction (e.g. 422 on placeOrder).
     * Skipped for SQLite :memory: where a second connection would be an empty database.
     */
    protected function registerHttpLogDatabaseConnection(): void
    {
        $connections = config('database.connections', []);
        if (isset($connections['http_logs'])) {
            return;
        }

        $defaultName = (string) config('database.default');
        $defaultConfig = $connections[$defaultName] ?? null;
        if (! is_array($defaultConfig)) {
            return;
        }

        $driver = (string) ($defaultConfig['driver'] ?? '');
        $database = (string) ($defaultConfig['database'] ?? '');
        if ($driver === 'sqlite' && $database === ':memory:') {
            return;
        }

        config(['database.connections.http_logs' => $defaultConfig]);
    }
}
