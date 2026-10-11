{{-- Flux Breadcrumbs guidelines for AI code assistants --}}
{{-- Source: https://github.com/TobiasNagel01/flux-breadcrumbs --}}
{{-- License: MIT --}}

## Flux Breadcrumbs

- `tobiasnagel/flux-breadcrumbs` renders route-driven breadcrumbs with the Flux UI breadcrumbs component. Breadcrumbs are declared on routes with the `Route::breadcrumb(string $label, ?string $parent = null, ?string $icon = null)` macro; `parent` is the full name of another route that also has a breadcrumb. Never build breadcrumb trails manually in controllers, Blade views or Livewire components.
- Labels may contain `{param.path}` placeholders resolved against the current route's bound parameters, for example `{user.name}`.
- Render the trail once in the page layout with `@include('flux-breadcrumbs::breadcrumbs')`. Keep the include outside Livewire components, otherwise breadcrumbs disappear after component updates.
- Always activate the `flux-breadcrumbs-development` skill when adding or changing breadcrumbs, when code references `->breadcrumb(`, or when debugging a `LogicException` or `UrlGenerationException` related to breadcrumbs.

@verbatim
<code-snippet name="Defining a breadcrumb chain on routes" lang="php">
Route::get('/dashboard', DashboardController::class)
    ->name('dashboard')
    ->breadcrumb('Dashboard', icon: 'home');

Route::get('/users', [UserController::class, 'index'])
    ->name('users.index')
    ->breadcrumb('Users', parent: 'dashboard');

Route::get('/users/{user}', [UserController::class, 'show'])
    ->name('users.show')
    ->breadcrumb('{user.name}', parent: 'users.index');
</code-snippet>
@endverbatim
