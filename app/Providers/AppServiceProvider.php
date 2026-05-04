<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

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
