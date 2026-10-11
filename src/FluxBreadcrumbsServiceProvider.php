<?php

namespace TobiasNagel\FluxBreadcrumbs;

use Illuminate\Routing\Route;
use Illuminate\Support\ServiceProvider;

class FluxBreadcrumbsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/flux-breadcrumbs.php', 'flux-breadcrumbs');

        $this->app->singleton(BreadcrumbResolver::class);
    }

    public function boot(): void
    {
        $this->registerRouteMacro();

        $this->loadViewsFrom(__DIR__.'/../resources/views', 'flux-breadcrumbs');

        $this->publishes([
            __DIR__.'/../config/flux-breadcrumbs.php' => config_path('flux-breadcrumbs.php'),
        ], 'flux-breadcrumbs-config');

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/flux-breadcrumbs'),
        ], 'flux-breadcrumbs-views');
    }

    /**
     * Registers `Route::breadcrumb(string $label, ?string $parent = null, ?string $icon = null)`.
     *
     * Only scalar values are stored on the route action so the configuration survives `route:cache`.
     */
    private function registerRouteMacro(): void
    {
        Route::macro('breadcrumb', function (string $label, ?string $parent = null, ?string $icon = null): Route {
            /** @var Route $this */
            return $this->setAction(array_merge($this->getAction(), [
                'breadcrumb' => ['label' => $label, 'parent' => $parent, 'icon' => $icon],
            ]));
        });
    }
}
