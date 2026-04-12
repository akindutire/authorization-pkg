<?php

namespace Akindutire\Authorization;

use Akindutire\Authorization\Console\Commands\ClearPermissionCache;
use Akindutire\Authorization\Console\Commands\WarmPermissionCache;
use Akindutire\Authorization\Middleware\ValidateSubjectAction;
use Akindutire\Authorization\Services\PermissionSvc;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;

class AuthorizationServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any package services.
     *
     * @return void
     */
    public function boot()
    {
        // Publish configuration file
        $this->publishes([
            __DIR__.'/../config/akindutire-authorization.php' => config_path('akindutire-authorization.php'),
        ], 'authorization-config');

        // Publish migrations
        $this->publishes([
            __DIR__.'/../database/migrations/' => database_path('migrations'),
        ], 'authorization-migrations');

        // Load migrations
        if ($this->app->runningInConsole()) {
            $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        }

        // Register middleware
        $router = $this->app->make(Router::class);
        $router->aliasMiddleware('validate.subject.action', ValidateSubjectAction::class);

        // Register artisan commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                WarmPermissionCache::class,
                ClearPermissionCache::class,
            ]);
        }
    }

    /**
     * Register any package services.
     *
     * @return void
     */
    public function register()
    {
        // Merge configuration
        $this->mergeConfigFrom(
            __DIR__.'/../config/akindutire-authorization.php',
            'akindutire-authorization'
        );

        // Register PermissionSvc (NOT as singleton for thread-safety)
        // Each App::make() call creates a fresh instance to support
        // different entity types with different permission column names
        $this->app->bind(PermissionSvc::class, function ($app) {
            return new PermissionSvc();
        });

        // Register facade alias
        $this->app->alias(PermissionSvc::class, 'entity-permission');
    }
}
