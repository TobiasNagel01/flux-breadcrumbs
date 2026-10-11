<?php

namespace TobiasNagel\FluxBreadcrumbs;

use BackedEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Illuminate\Support\Arr;
use LogicException;
use Stringable;
use UnitEnum;

/**
 * Resolves the breadcrumb trail for a route configured via the `Route::breadcrumb()` macro.
 *
 * Each route may carry an action entry of the shape
 * `['breadcrumb' => ['label' => string, 'parent' => ?string, 'icon' => ?string]]`. The parent
 * references another route by its full name. Labels may contain placeholders such as
 * `{adminUser.user.name}` which are resolved against the bound parameters of the current route.
 * When an icon is set, the item is rendered as that icon and the label is used as its accessible name.
 */
class BreadcrumbResolver
{
    public function __construct(private readonly Router $router) {}

    /**
     * @return array<int, BreadcrumbItem>
     */
    public function resolve(?Route $route): array
    {
        if ($route === null || $this->configurationFor($route) === null) {
            return [];
        }

        $parameters = $route->parameters();
        $items = [];
        $visited = [];
        $current = $route;

        while ($current !== null) {
            $configuration = $this->configurationFor($current);

            if ($configuration === null) {
                throw new LogicException(sprintf('Route [%s] has no breadcrumb configuration.', $current->getName()));
            }

            $name = $current->getName();

            if ($name !== null && in_array($name, $visited, true)) {
                throw new LogicException(sprintf('Breadcrumb parent chain contains a cycle at route [%s].', $name));
            }

            $visited[] = $name;

            $items[] = new BreadcrumbItem(
                label: $this->resolveLabel($configuration['label'], $parameters),
                url: $current === $route ? null : $this->urlFor($current, $parameters),
                icon: $configuration['icon'] ?? null,
            );

            $current = $this->parentOf($current, $configuration['parent']);
        }

        return array_reverse($items);
    }

    /**
     * @return array{label: string, parent: ?string, icon: ?string}|null
     */
    private function configurationFor(Route $route): ?array
    {
        return $route->getAction('breadcrumb');
    }

    private function parentOf(Route $route, ?string $parentName): ?Route
    {
        if ($parentName === null) {
            return null;
        }

        $parent = $this->router->getRoutes()->getByName($parentName);

        if ($parent === null) {
            throw new LogicException(sprintf('Breadcrumb parent route [%s] referenced by [%s] does not exist.', $parentName, $route->getName()));
        }

        return $parent;
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    private function resolveLabel(string $label, array $parameters): string
    {
        return (string) preg_replace_callback(
            '/\{([a-zA-Z0-9_.]+)\}/',
            fn (array $matches): string => $this->stringify($this->resolvePath($parameters, $matches[1])),
            $label,
        );
    }

    /**
     * Walks a dot-notation path. Relationships on Eloquent models that are not loaded yet are
     * eager-loaded explicitly, so the lookup does not trip `Model::preventLazyLoading()`.
     */
    private function resolvePath(mixed $target, string $path): mixed
    {
        foreach (explode('.', $path) as $segment) {
            $target = $target instanceof Model
                ? $this->resolveModelSegment($target, $segment)
                : data_get($target, $segment);

            if ($target === null) {
                return null;
            }
        }

        return $target;
    }

    private function resolveModelSegment(Model $model, string $segment): mixed
    {
        if (
            array_key_exists($segment, $model->getAttributes())
            || $model->hasGetMutator($segment)
            || $model->hasAttributeMutator($segment)
            || $model->hasCast($segment)
        ) {
            return $model->getAttribute($segment);
        }

        if ($model->relationLoaded($segment)) {
            return $model->getRelation($segment);
        }

        if ($model->isRelation($segment)) {
            $model->load($segment);

            return $model->getRelation($segment);
        }

        return null;
    }

    private function stringify(mixed $value): string
    {
        return match (true) {
            $value instanceof BackedEnum => (string) $value->value,
            $value instanceof UnitEnum => $value->name,
            $value instanceof Stringable => (string) $value,
            is_scalar($value) => (string) $value,
            default => '',
        };
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    private function urlFor(Route $route, array $parameters): string
    {
        return route($route->getName(), Arr::only($parameters, $route->parameterNames()));
    }
}
