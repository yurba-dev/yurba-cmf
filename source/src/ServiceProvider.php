<?php

namespace Yurba\Cmf;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;
use Yurba\Cmf\Http\Middleware\Authorize;
use Yurba\Cmf\Http\Middleware\HandleRedirects;

class ServiceProvider extends BaseServiceProvider
{
    public function register(): void
    {
        require_once __DIR__.'/helpers.php';

        $this->mergeConfigFrom(__DIR__.'/../config/yurba.php', 'yurba');

        $this->app->singleton('yurba.cmf', fn () => new Panel());
        $this->app->alias('yurba.cmf', Panel::class);
    }

    public function boot(Router $router): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'yurba');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        // string-keyed JSON translations (lang/{locale}.json); English needs no file.
        $this->loadJsonTranslationsFrom(__DIR__.'/../lang');
        $router->aliasMiddleware('yurba.auth', Authorize::class);

        View::composer('yurba::*', function ($view) {
            $panel = app('yurba.cmf');
            $view->with('yurbaBrand', $panel->brand());
            $view->with('yurbaLogo', $panel->logo());
            $view->with('yurbaHideBrand', $panel->hideBrandText());
            $view->with('yurbaAccent', $panel->accent());
            $view->with('yurbaActionIcons', $panel->actionIcons());
            $view->with('yurbaStyles', $panel->styles());
            $view->with('yurbaScripts', $panel->scripts());
            $view->with('yurbaHead', $panel->head());
            $view->with('yurbaFoot', $panel->foot());
            $view->with('yurbaResources', $panel->authorizedResources());
            $view->with('yurbaSettings', $panel->settingsPages());
            $view->with('yurbaPages', $panel->pages());
            $view->with('yurbaUser', auth()->guard($panel->guard())->user());
        });

        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');

        // Apply admin-managed redirects globally, so they fire even for paths
        // that no longer resolve to a route. Skipped on the console.
        if (config('yurba.redirects.enabled', true) && ! $this->app->runningInConsole()) {
            $this->app->make(\Illuminate\Contracts\Http\Kernel::class)
                ->pushMiddleware(HandleRedirects::class);
        }

        if ($this->app->runningInConsole()) {
            $this->commands([
                Console\InstallCommand::class,
                Console\ResourceMakeCommand::class,
                Console\FieldMakeCommand::class,
                Console\SettingsMakeCommand::class,
                Console\MediaOptimizeCommand::class,
                Console\PublishScheduledCommand::class,
            ]);
        }

        // auto-publish due scheduled records every minute (needs the app's cron
        // to run `schedule:run`). disable with config('yurba.publish_scheduler').
        if (config('yurba.publish_scheduler', true)) {
            $this->app->booted(function () {
                $this->app->make(Schedule::class)->command('yurba:publish-scheduled')->everyMinute();
            });
        }

        $this->publishes([
            __DIR__.'/../config/yurba.php' => config_path('yurba.php'),
        ], 'yurba-config');

        $this->publishes([
            __DIR__.'/../resources/dist' => public_path('vendor/yurba'),
        ], 'yurba-assets');

        $this->publishes([
            __DIR__.'/../lang' => $this->app->langPath(),
        ], 'yurba-lang');
    }
}
