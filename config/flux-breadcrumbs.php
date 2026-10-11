<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Navigation Mode
    |--------------------------------------------------------------------------
    |
    | When enabled, breadcrumb links are rendered with `wire:navigate` so that
    | Livewire performs an SPA-style navigation. Disable this to render plain
    | links that trigger a full page load.
    |
    */

    'wire_navigate' => true,

    /*
    |--------------------------------------------------------------------------
    | Separator
    |--------------------------------------------------------------------------
    |
    | The separator rendered between items. `null` uses the Flux default
    | (chevron). Use `slash` for slashes or any Heroicon name for a custom icon.
    |
    */

    'separator' => null,

    /*
    |--------------------------------------------------------------------------
    | Icon Variant
    |--------------------------------------------------------------------------
    |
    | The Flux icon variant used for items configured with an icon.
    | Supported: `outline`, `solid`, `mini`, `micro`.
    |
    */

    'icon_variant' => 'mini',

    /*
    |--------------------------------------------------------------------------
    | Minimum Items
    |--------------------------------------------------------------------------
    |
    | The breadcrumbs are only rendered when the trail contains at least this
    | many items. Set to 1 to always show the trail, even on root pages.
    |
    */

    'min_items' => 1,

];
