<?php

namespace TobiasNagel\FluxBreadcrumbs\Tests;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use TobiasNagel\FluxBreadcrumbs\BreadcrumbResolver;

class FluxBreadcrumbsServiceProviderTest extends TestCase
{
    public function test_it_merges_the_default_configuration(): void
    {
        $this->assertTrue(config('flux-breadcrumbs.wire_navigate'));
        $this->assertNull(config('flux-breadcrumbs.separator'));
        $this->assertSame('mini', config('flux-breadcrumbs.icon_variant'));
        $this->assertSame(1, config('flux-breadcrumbs.min_items'));
    }

    public function test_it_registers_the_resolver_as_a_singleton(): void
    {
        $this->assertSame(
            $this->app->make(BreadcrumbResolver::class),
            $this->app->make(BreadcrumbResolver::class),
        );
    }

    public function test_it_registers_the_view_namespace(): void
    {
        $this->assertTrue(View::exists('flux-breadcrumbs::breadcrumbs'));
    }

    public function test_it_publishes_config_and_views(): void
    {
        $config = ServiceProvider::pathsToPublish(null, 'flux-breadcrumbs-config');
        $views = ServiceProvider::pathsToPublish(null, 'flux-breadcrumbs-views');

        $this->assertSame([config_path('flux-breadcrumbs.php')], array_values($config));
        $this->assertSame([resource_path('views/vendor/flux-breadcrumbs')], array_values($views));
        $this->assertFileExists(array_key_first($config));
        $this->assertDirectoryExists(array_key_first($views));
    }

    public function test_the_breadcrumb_macro_stores_scalar_configuration_on_the_route_action(): void
    {
        $route = Route::get('/users', fn () => 'ok')
            ->name('users.index')
            ->breadcrumb('Users', parent: 'dashboard', icon: 'users');

        $this->assertSame(
            ['label' => 'Users', 'parent' => 'dashboard', 'icon' => 'users'],
            $route->getAction('breadcrumb'),
        );
        $this->assertSame('users.index', $route->getName());
    }

    public function test_the_breadcrumb_macro_defaults_parent_and_icon_to_null(): void
    {
        $route = Route::get('/', fn () => 'ok')->breadcrumb('Home');

        $this->assertSame(
            ['label' => 'Home', 'parent' => null, 'icon' => null],
            $route->getAction('breadcrumb'),
        );
    }

    public function test_the_breadcrumb_macro_keeps_existing_action_entries(): void
    {
        $route = Route::get('/', fn () => 'ok')->middleware('web')->breadcrumb('Home');

        $this->assertSame(['web'], $route->getAction('middleware'));
        $this->assertNotNull($route->getAction('breadcrumb'));
    }
}
