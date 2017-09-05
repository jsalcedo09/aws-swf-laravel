<?php

namespace Jsalcedo09\SwfWorkflows;

use Illuminate\Support\ServiceProvider;
use Illuminate\Foundation\AliasLoader;
use Jsalcedo09\SwfWorkflows\Commands\DeciderCommand;
use Jsalcedo09\SwfWorkflows\Commands\ActivityCommand;
use Jsalcedo09\SwfWorkflows\Commands\RegisterCommand;



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
            __DIR__.'/../config/swfworkflows.php' => config_path('swfworkflows.php'),
            __DIR__.'/../config/activityworkflow.php' => config_path('activityworkflow.php'),
        ]);

        if ($this->app->runningInConsole()) {
            $this->commands([
                DeciderCommand::class,
                ActivityCommand::class,
                RegisterCommand::class,
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
            __DIR__.'/../config/swfworkflows.php', 'swfworkflows'
        );

        $this->app->register('Aws\Laravel\AwsServiceProvider');
        AliasLoader::getInstance()->alias('AWS', 'Aws\Laravel\AwsFacade');
    }
}
