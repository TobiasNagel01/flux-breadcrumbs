@inject('breadcrumbResolver', TobiasNagel\FluxBreadcrumbs\BreadcrumbResolver::class)

@php
    $breadcrumbs = $breadcrumbResolver->resolve(request()->route());
    $wireNavigate = (bool) config('flux-breadcrumbs.wire_navigate', true);
    $separator = config('flux-breadcrumbs.separator');
    $iconVariant = config('flux-breadcrumbs.icon_variant', 'mini');
    $minItems = max(1, (int) config('flux-breadcrumbs.min_items', 1));
@endphp

@if (count($breadcrumbs) >= $minItems)
    <flux:breadcrumbs>
        @foreach ($breadcrumbs as $breadcrumb)
            @if ($breadcrumb->url !== null)
                <flux:breadcrumbs.item
                    :href="$breadcrumb->url"
                    :icon="$breadcrumb->icon"
                    :icon:variant="$iconVariant"
                    :separator="$separator"
                    :title="$breadcrumb->icon ? $breadcrumb->label : null"
                    :aria-label="$breadcrumb->icon ? $breadcrumb->label : null"
                    :wire:navigate="$wireNavigate"
                >{{ $breadcrumb->label }}</flux:breadcrumbs.item>
            @else
                <flux:breadcrumbs.item
                    :icon="$breadcrumb->icon"
                    :icon:variant="$iconVariant"
                    :separator="$separator"
                    :title="$breadcrumb->icon ? $breadcrumb->label : null"
                    :aria-label="$breadcrumb->icon ? $breadcrumb->label : null"
                >{{ $breadcrumb->label }}</flux:breadcrumbs.item>
            @endif
        @endforeach
    </flux:breadcrumbs>
@endif
