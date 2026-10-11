---
name: flux-breadcrumbs-development
description: Define and render route-driven breadcrumbs with tobiasnagel/flux-breadcrumbs, including the Route::breadcrumb macro, parent chains, placeholder labels, icons, configuration and Livewire layout placement.
license: MIT
---

# Flux Breadcrumbs Development

## Overview

`tobiasnagel/flux-breadcrumbs` renders breadcrumbs from route definitions. Each route declares a label, an optional parent route name and an optional icon via the `Route::breadcrumb()` macro. The package walks the parent chain of the current route and renders it with Flux UI's `<flux:breadcrumbs>` component. There are no breadcrumb files and no manual trail building.

Requirements: PHP 8.4+, Laravel 13, Flux UI 2.x (`livewire/flux`) installed separately.

## When to activate

- Adding, changing or debugging breadcrumbs in a Laravel app that uses this package.
- Code references `->breadcrumb(` on a route or `@include('flux-breadcrumbs::breadcrumbs')`.
- A `LogicException` mentioning "breadcrumb" or an `UrlGenerationException` raised while rendering a layout.

## Core rules

1. Breadcrumbs live on routes only. Never build trails in controllers, Blade views or Livewire components.
2. `parent` is the **full route name** of another route that itself has a breadcrumb.
3. The include belongs in the page layout, outside any Livewire component.
4. Parent URLs are generated with `route()` using the current route's parameters with matching names. Nothing is inferred from models.

## Defining breadcrumbs

Macro signature:

```php
Route::breadcrumb(string $label, ?string $parent = null, ?string $icon = null)
```

Chain routes by naming the parent:

```php
use Illuminate\Support\Facades\Route;

Route::get('/dashboard', DashboardController::class)
    ->name('dashboard')
    ->breadcrumb('Dashboard', icon: 'home');

Route::get('/users', [UserController::class, 'index'])
    ->name('users.index')
    ->breadcrumb('Users', parent: 'dashboard');

Route::get('/users/{user}', [UserController::class, 'show'])
    ->name('users.show')
    ->breadcrumb('{user.name}', parent: 'users.index');
```

Visiting `/users/42` renders `Home icon › Users › Jane Doe`. The current page is plain text, parents are links.

Only scalar values are stored on the route action, so breadcrumbs are compatible with `php artisan route:cache`. Do not pass closures or objects.

## Dynamic labels

Placeholders in curly braces are resolved against the bound parameters of the **current** route using dot notation, so route-model-bound instances and their relationships are reachable:

```php
->breadcrumb('{user.name}', parent: 'users.index')
->breadcrumb('{project.team.name} / {project.title}', parent: 'projects.index')
```

Resolution rules:

- Placeholders that cannot be resolved become an empty string. No exception is thrown.
- Scalars and `Stringable` values are cast to string.
- Backed enums resolve to their backing value, unit enums to their case name. `{status.value}` and `{post.status.name}` select explicitly. This applies to implicitly bound route enums and enum-valued model attributes.
- Any other value becomes an empty string.
- Relationships that are not loaded yet are eager-loaded when the label is resolved. This works with `Model::preventLazyLoading()` but costs one query per unloaded relationship. Eager-load relationships used in labels where that matters.

## Parent route parameters

The parent URL is built with `route($parentName, $parameters)` where `$parameters` contains only those current-route parameters whose names match parameters declared by the parent route. Laravel URL defaults (`URL::defaults()`) are honoured.

This works for nested resources:

```php
Route::get('/projects/{project}/tasks', ...)->name('projects.tasks.index')->breadcrumb('Tasks', parent: 'projects.show');
Route::get('/projects/{project}/tasks/{task}', ...)->name('projects.tasks.show')->breadcrumb('{task.title}', parent: 'projects.tasks.index');
```

It does **not** work for shallow routes. A child route `/tasks/{task}` cannot link to a parent `/projects/{project}/tasks` even if `$task->project` exists. The package does not infer parameters from relationships and has no parameter mapping. A missing required parameter throws `Illuminate\Routing\Exceptions\UrlGenerationException` during rendering.

Fixes, in order of preference:

1. Use a nested child route that carries the parent parameter (`/projects/{project}/tasks/{task}`).
2. Choose a parent whose parameters are all available on the child route.
3. Supply the missing parameter through `URL::defaults()` (for example in middleware).

## Rendering

Include the bundled view once in the page layout:

```blade
@include('flux-breadcrumbs::breadcrumbs')
```

- The view resolves the trail from `request()->route()`. Nothing is passed in.
- Routes without a breadcrumb definition render nothing.
- The current page is rendered as plain text, not as a link.

### Livewire

Place the include in the page layout, outside the component's re-rendered view. During a Livewire update the current request belongs to Livewire's update endpoint, which has no breadcrumb definition. Breadcrumbs included inside a component therefore disappear after the first update. The package does not preserve the original page-route context.

## Icons

Pass any Heroicon name supported by Flux as the third argument. The label is still passed to the item and is also used as its `title` and `aria-label`, so the item stays accessible:

```php
->breadcrumb('Dashboard', icon: 'home')
```

The icon variant is controlled by the `icon_variant` config option.

## Configuration

Publish with `php artisan vendor:publish --tag=flux-breadcrumbs-config`. File: `config/flux-breadcrumbs.php`.

| Key | Default | Description |
| --- | --- | --- |
| `wire_navigate` | `true` | Render parent links with `wire:navigate`. Set to `false` for full page loads. |
| `separator` | `null` | Separator between items. `null` uses the Flux default chevron. Use `slash` or any Heroicon name. |
| `icon_variant` | `mini` | Flux icon variant for items with an icon: `outline`, `solid`, `mini` or `micro`. |
| `min_items` | `1` | Only render when the trail has at least this many items. Set to `2` to hide it on root pages. |

## Custom markup

Publish the view with `php artisan vendor:publish --tag=flux-breadcrumbs-views` and edit `resources/views/vendor/flux-breadcrumbs/breadcrumbs.blade.php`.

To build your own markup, resolve the trail yourself:

```php
use TobiasNagel\FluxBreadcrumbs\BreadcrumbResolver;

$items = app(BreadcrumbResolver::class)->resolve(request()->route());

foreach ($items as $item) {
    $item->label; // string
    $item->url;   // ?string, null for the current page
    $item->icon;  // ?string
}
```

`resolve()` accepts `?Route` and returns `array<int, BreadcrumbItem>` ordered from root to current page. It returns an empty array when the route is `null` or has no breadcrumb definition. In Blade, `@inject('breadcrumbResolver', TobiasNagel\FluxBreadcrumbs\BreadcrumbResolver::class)` is the pattern used by the bundled view.

## Errors and pitfalls

A `LogicException` is thrown while resolving when:

- `parent` references a route name that does not exist,
- a route in the parent chain has no breadcrumb definition,
- the parent chain contains a cycle.

Other common mistakes:

- Breadcrumbs render nothing: the current route has no `->breadcrumb()` call, or `min_items` is higher than the trail length.
- Breadcrumbs vanish after a Livewire interaction: the include sits inside the component view instead of the layout.
- `UrlGenerationException`: a parent route needs a parameter the current route does not have under the same name (see "Parent route parameters").
- Empty label segment: the placeholder path does not exist on the bound parameter or resolves to a non-scalar value.
- `<flux:breadcrumbs>` component not found: Flux UI is not installed. Run `composer require livewire/flux`.

## Checklist

- [ ] Every route in the chain has a name and a `->breadcrumb()` call.
- [ ] Each `parent` is a full, existing route name with its own breadcrumb.
- [ ] Every parent route's parameters exist under the same names on the child route, or are provided via URL defaults.
- [ ] `@include('flux-breadcrumbs::breadcrumbs')` sits in the page layout, not inside a Livewire component.
- [ ] Labels use `{param.path}` placeholders instead of closures or computed strings.
