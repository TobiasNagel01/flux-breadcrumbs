<?php

namespace TobiasNagel\FluxBreadcrumbs\Tests;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;

class BreadcrumbsViewTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Mimic how Flux wires up its `<flux:*>` tags, backed by minimal stub components.
        View::addNamespace('fluxstub', __DIR__.'/Fixtures/views');
        Blade::anonymousComponentNamespace('fluxstub::components', 'flux');
        Blade::prepareStringsForCompilationUsing(
            fn (string $string): string => (string) preg_replace('/<(\/?)flux:/', '<$1x-flux::', $string),
        );

        Route::get('/dashboard', fn () => view('fluxstub::page'))->name('dashboard')->breadcrumb('Dashboard', icon: 'home');
        Route::get('/users', fn () => view('fluxstub::page'))->name('users.index')->breadcrumb('Users', parent: 'dashboard');
        Route::get('/plain', fn () => view('fluxstub::page'))->name('plain');

        $this->app['router']->getRoutes()->refreshNameLookups();

        // Make sure no compiled view from a run without the precompiler is reused.
        $this->artisan('view:clear');
    }

    public function test_it_renders_the_trail_for_the_current_route(): void
    {
        $html = $this->get('/users')->assertOk()->getContent();

        $this->assertStringContainsString('<nav data-breadcrumbs>', $html);
        $this->assertMatchesRegularExpression(
            '#<li[^>]*href="http://localhost/dashboard"[^>]*>Dashboard</li>#',
            $html,
        );
        $this->assertMatchesRegularExpression('#<li[^>]*icon="home"[^>]*>Dashboard</li>#', $html);
        $this->assertMatchesRegularExpression('#<li[^>]*title="Dashboard"[^>]*>Dashboard</li>#', $html);
        $this->assertMatchesRegularExpression('#<li[^>]*aria-label="Dashboard"[^>]*>Dashboard</li>#', $html);
        $this->assertMatchesRegularExpression('#<li[^>]*icon:variant="mini"[^>]*>Dashboard</li>#', $html);
        $this->assertMatchesRegularExpression('#<li[^>]*wire:navigate[^>]*>Dashboard</li>#', $html);

        $this->assertMatchesRegularExpression('#<li[^>]*>Users</li>#', $html);
        $this->assertDoesNotMatchRegularExpression('#<li[^>]*href=[^>]*>Users</li>#', $html);
        $this->assertDoesNotMatchRegularExpression('#<li[^>]*title=[^>]*>Users</li>#', $html);
    }

    public function test_it_respects_configuration(): void
    {
        config()->set('flux-breadcrumbs.wire_navigate', false);
        config()->set('flux-breadcrumbs.separator', 'slash');
        config()->set('flux-breadcrumbs.icon_variant', 'solid');

        $html = $this->get('/users')->assertOk()->getContent();

        $this->assertStringNotContainsString('wire:navigate', $html);
        $this->assertStringContainsString('separator="slash"', $html);
        $this->assertStringContainsString('icon:variant="solid"', $html);
    }

    public function test_it_hides_the_trail_below_the_minimum_item_count(): void
    {
        config()->set('flux-breadcrumbs.min_items', 2);

        $this->assertStringNotContainsString('data-breadcrumbs', $this->get('/dashboard')->getContent());
        $this->assertStringContainsString('data-breadcrumbs', $this->get('/users')->getContent());
    }

    public function test_it_renders_nothing_for_routes_without_breadcrumbs(): void
    {
        $html = $this->get('/plain')->assertOk()->getContent();

        $this->assertSame('', trim($html));
    }
}
