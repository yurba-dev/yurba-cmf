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
        $this->loadJsonTranslationsFrom(__DIR__.'/../lang');
        $router->aliasMiddleware('yurba.auth', Authorize::class);
        $router->aliasMiddleware('yurba.locale', \Yurba\Cmf\Http\Middleware\SetLocale::class);

        // every yurba partial (cells, row actions) hits this composer, so the panel data is built once per request
        $shared = new \WeakMap();
        View::composer('yurba::*', function ($view) use ($shared) {
            $request = request();
            if (! isset($shared[$request])) {
                $panel = app('yurba.cmf');
                $shared[$request] = [
                    'yurbaBrand' => $panel->brand(),
                    'yurbaLogo' => $panel->logo(),
                    'yurbaHideBrand' => $panel->hideBrandText(),
                    'yurbaAccent' => $panel->accent(),
                    'yurbaActionIcons' => $panel->actionIcons(),
                    'yurbaStyles' => $panel->styles(),
                    'yurbaScripts' => $panel->scripts(),
                    'yurbaHead' => $panel->head(),
                    'yurbaFoot' => $panel->foot(),
                    'yurbaResources' => $panel->authorizedResources(),
                    'yurbaSettings' => $panel->settingsPages(),
                    'yurbaPages' => $panel->pages(),
                    'yurbaUser' => auth()->guard($panel->guard())->user(),
                ];
            }
            $view->with($shared[$request]);
        });

        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');

        // global so redirects fire even for paths that no longer resolve to a route
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
