# Flux Breadcrumbs

Route-driven breadcrumbs for Laravel applications rendered with [Flux UI](https://fluxui.dev).

Declare a label, an optional parent and an optional icon directly on your routes. The package walks the parent chain of the current route and renders it with Flux's `<flux:breadcrumbs>` component. No breadcrumb files, no manual trail building.

## Requirements

- PHP 8.4+
- Laravel 13
- Flux UI 2.x (`livewire/flux`), installed separately. It is only *suggested* by this package but is required to render the bundled view.

## Installation

```bash
composer require tobiasnagel/flux-breadcrumbs
```

The service provider is registered automatically via package discovery.

Optionally publish the config file:

```bash
php artisan vendor:publish --tag=flux-breadcrumbs-config
```

## Usage

### Defining breadcrumbs

The package registers a `breadcrumb()` macro on routes:

```php
Route::breadcrumb(string $label, ?string $parent = null, ?string $icon = null)
```

`$parent` is the **full name** of another route that also has a breadcrumb. Chain routes together to build the trail:

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

Visiting `/users/42` renders:

```
🏠 › Users › Jane Doe
```

Only scalar values are stored on the route action, so breadcrumbs work with `php artisan route:cache`.

### Rendering

Include the bundled view wherever you want the trail to appear, typically in a layout:

```blade
@include('flux-breadcrumbs::breadcrumbs')
```

The view resolves the trail from the current request's route, so nothing needs to be passed in. Routes without a breadcrumb definition render nothing.

### Dynamic labels

Labels may contain placeholders in curly braces. They are resolved against the bound parameters of the **current** route using dot notation, so you can reach into route-model-bound instances:

```php
->breadcrumb('{user.name}', parent: 'users.index')
->breadcrumb('{project.team.name} / {project.title}', parent: 'projects.index')
```

Placeholders that cannot be resolved are replaced with an empty string.

Parent URLs are generated with `route()` using only the parameters the parent route itself declares, so nested resource routes work out of the box.

### Icons

Pass an icon name (any Heroicon supported by Flux) as the third argument. The label is still passed to the item and is also used as its `title` and `aria-label`, keeping the item accessible:

```php
->breadcrumb('Dashboard', icon: 'home')
```

The icon variant is controlled by the `icon_variant` config option.

## Configuration

`config/flux-breadcrumbs.php`:

| Key | Default | Description |
| --- | --- | --- |
| `wire_navigate` | `true` | Render links with `wire:navigate` for SPA-style navigation. Set to `false` for full page loads. |
| `separator` | `null` | Separator between items. `null` uses the Flux default chevron. Use `slash` or any Heroicon name. |
| `icon_variant` | `mini` | Flux icon variant for items with an icon: `outline`, `solid`, `mini` or `micro`. |
| `min_items` | `1` | Only render the trail when it contains at least this many items. Set to `2` to hide it on root pages. |

## Customising the view

Publish the Blade view to `resources/views/vendor/flux-breadcrumbs` and edit it freely:

```bash
php artisan vendor:publish --tag=flux-breadcrumbs-views
```

If you prefer to build your own markup, inject `TobiasNagel\FluxBreadcrumbs\BreadcrumbResolver` and call `resolve($route)`. It returns an array of `BreadcrumbItem` objects with public `label`, `url` and `icon` properties. The current page has a `null` URL.

## Behaviour

- The current page is rendered as plain text, not as a link.
- A `LogicException` is thrown when:
  - a `parent` references a route name that does not exist,
  - a route in the parent chain has no breadcrumb definition,
  - the parent chain contains a cycle.

## License

MIT
