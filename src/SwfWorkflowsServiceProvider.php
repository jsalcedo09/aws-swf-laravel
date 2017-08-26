<?php

namespace Jsalcedo09\SwfWorkflows;

use Illuminate\Support\ServiceProvider;
use Jsalcedo09\SwfWorkflows\Commands\DeciderCommand;

class SwfWorkflowsServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        //
        $this->publishes([
            __DIR__.'/config/swfworkflows.php' => config_path('swfworkflows.php'),
        ]);

        //If required migration uncomment
        //$this->loadMigrationsFrom(__DIR__.'/migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([
                DeciderCommand::class,
            ]);
        }
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__.'/config/swfworkflows.php', 'swfworkflows'
        );

        $this->app->register(
            'Aws\Laravel\AwsServiceProvider'
        );

        $loader = \Illuminate\Foundation\AliasLoader::getInstance();
        $loader->alias('AWS', 'Aws\Laravel\AwsFacade');
    }
}
