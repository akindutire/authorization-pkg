<?php

namespace Akindutire\Authorization;

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
            __DIR__.'/../config/authorization.php' => config_path('authorization.php'),
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
            __DIR__.'/../config/authorization.php',
            'authorization'
        );

        // Register PermissionSvc as singleton
        $this->app->singleton(PermissionSvc::class, function ($app) {
            return new PermissionSvc();
        });

        // Register facade alias
        $this->app->alias(PermissionSvc::class, 'entity-permission');
    }
}
