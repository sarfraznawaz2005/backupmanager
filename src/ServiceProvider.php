<?php

namespace Sarfraznawaz2005\BackupManager;

use Illuminate\Support\ServiceProvider as BaseServiceProvider;
use Sarfraznawaz2005\BackupManager\Console\BackupCommand;
use Sarfraznawaz2005\BackupManager\Console\BackupListCommand;
use Sarfraznawaz2005\BackupManager\Console\BackupRestoreCommand;

class ServiceProvider extends BaseServiceProvider
{
    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        // routes
        if (!$this->app->routesAreCached()) {
            require __DIR__ . '/Http/routes.php';
        }

        // views
        $this->loadViewsFrom(__DIR__ . '/Views', 'backupmanager');

        // Publishing is only necessary when using the CLI.
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/Config/config.php' => config_path('backupmanager.php'),
                __DIR__ . '/Migrations' => database_path('migrations')
            ]);
        }
    }

    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/Config/config.php', 'BackupManager');

        $this->app->bind('command.backupmanager.create', BackupCommand::class);
        $this->commands('command.backupmanager.create');

        $this->app->bind('command.backupmanager.list', BackupListCommand::class);
        $this->commands('command.backupmanager.list');

        $this->app->bind('command.backupmanager.restore', BackupRestoreCommand::class);
        $this->commands('command.backupmanager.restore');

        $this->app->singleton('BackupManager', function () {
            return $this->app->make(BackupManager::class);
        });
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return ['BackupManager'];
    }
}
