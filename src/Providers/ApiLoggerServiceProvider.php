<?php

namespace GDI\ApiLogger\Providers;

use Illuminate\Support\ServiceProvider;
use GDI\ApiLogger\Console\ClearLogsCommand;
use GDI\ApiLogger\Console\ImportRulesCommand;
use GDI\ApiLogger\Rules\RuleRepository;
use GDI\ApiLogger\Contracts\ApiLoggerInterface;
use GDI\ApiLogger\Drivers\DatabaseLogger;
use GDI\ApiLogger\Drivers\FileLogger;
use GDI\ApiLogger\Exceptions\InvalidApiLogDriverException;
use GDI\ApiLogger\Http\Middleware\ApiLoggerMiddleware;

class ApiLoggerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Merge default config
        $this->mergeConfigFrom(__DIR__ . '/../../config/apilogger.php', 'apilogger');

        // Bind the driver to the interface
        $this->app->singleton(ApiLoggerInterface::class, function ($app) {
            $driver = config('apilogger.driver', 'file');

            return match (true) {
                $driver === 'db'       => new DatabaseLogger(),
                $driver === 'file'     => new FileLogger(),
                class_exists($driver)  => new $driver(),
                default                => throw new InvalidApiLogDriverException("ApiLogger: unsupported driver [{$driver}]")
            };
        });

        // Payload/response storage rules (config + optional database table)
        $this->app->singleton(RuleRepository::class);

        // Register middleware alias
        $this->app['router']->aliasMiddleware('apilogger', ApiLoggerMiddleware::class);
    }

    public function boot(): void
    {
        // Views
        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'apilogger');

        // Migrations
        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');

        // Routes
        $this->loadRoutesFrom(__DIR__ . '/../../routes/web.php');

        // Publishables
        if ($this->app->runningInConsole()) {
            // Config
            $this->publishes([
                __DIR__ . '/../../config/apilogger.php' => config_path('apilogger.php'),
            ], 'apilogger-config');

            // Views
            $this->publishes([
                __DIR__ . '/../../resources/views' => resource_path('views/vendor/apilogger'),
            ], 'apilogger-views');

            // Migrations
            $this->publishes([
                __DIR__ . '/../../database/migrations' => database_path('migrations'),
            ], 'apilogger-migrations');

            // Register artisan command
            $this->commands([ClearLogsCommand::class, ImportRulesCommand::class]);
        }
    }
}
