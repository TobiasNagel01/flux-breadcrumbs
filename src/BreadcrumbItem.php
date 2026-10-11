<?php

namespace TobiasNagel\FluxBreadcrumbs;

final readonly class BreadcrumbItem
{
    public function __construct(
        public string $label,
        public ?string $url = null,
        public ?string $icon = null,
    ) {}
}
