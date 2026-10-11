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

Include the bundled view in your page layout or a regular Blade view:

```blade
@include('flux-breadcrumbs::breadcrumbs')
```

The view resolves the trail from the current request's route, so nothing needs to be passed in. Routes without a breadcrumb definition render nothing.

When using Livewire, place the include in the page layout, outside the component's re-rendered view. During component updates, the current request belongs to Livewire's update endpoint rather than the original page route. That endpoint normally has no breadcrumb definition, so breadcrumbs included inside the component disappear after an update. The package does not preserve the original page-route context.

### Dynamic labels

Labels may contain placeholders in curly braces. They are resolved against the bound parameters of the **current** route using dot notation, so you can reach into route-model-bound instances:

```php
->breadcrumb('{user.name}', parent: 'users.index')
->breadcrumb('{project.team.name} / {project.title}', parent: 'projects.index')
```

Placeholders that cannot be resolved are replaced with an empty string.

Resolved values are cast directly to strings and must be scalar or stringable. Enum instances cannot be cast to strings: use `.value` for a backed enum's value or `.name` for its case name. This applies both to implicitly bound route enums and enum-valued model attributes:

```php
->breadcrumb('{status.value}')
->breadcrumb('{post.status.name}')
```

Using `{status}` or `{post.status}` when the value is an enum throws an `Error` during rendering.

### Parent route parameters

Parent URLs are generated with `route()` using only current-route parameters whose names match parameters declared by the parent route. Nested resource routes work when the required parent parameters are available under the same names, or supplied by Laravel URL defaults.

The package does not infer missing parameters from model relationships or support parameter mappings. For example, a shallow child route `/tasks/{task}` cannot link to a breadcrumb parent `/projects/{project}/tasks` unless `project` is otherwise supplied, even if the bound task has a `project` relationship. A missing required parameter causes an `Illuminate\Routing\Exceptions\UrlGenerationException` during rendering.

Use a nested child route such as `/projects/{project}/tasks/{task}`, choose a parent whose parameters are available, or configure the required Laravel URL defaults.

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
