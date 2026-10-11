<?php

namespace TobiasNagel\FluxBreadcrumbs\Tests;

use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use Orchestra\Testbench\TestCase as Orchestra;
use TobiasNagel\FluxBreadcrumbs\FluxBreadcrumbsServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [FluxBreadcrumbsServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }

    protected function route(string $uri, string $name): Route
    {
        return RouteFacade::get($uri, fn () => 'ok')->name($name);
    }

    /**
     * Binds a route to a request so that `parameters()` can be read, then sets the given parameters.
     *
     * @param  array<string, mixed>  $parameters
     */
    protected function bind(Route $route, string $uri, array $parameters = []): Route
    {
        $this->app['router']->getRoutes()->refreshNameLookups();

        $route->bind(Request::create($uri));

        foreach ($parameters as $key => $value) {
            $route->setParameter($key, $value);
        }

        return $route;
    }
}
