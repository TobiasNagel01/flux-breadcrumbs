<?php

namespace TobiasNagel\FluxBreadcrumbs\Tests;

use PHPUnit\Framework\TestCase;
use TobiasNagel\FluxBreadcrumbs\BreadcrumbItem;

class BreadcrumbItemTest extends TestCase
{
    public function test_it_exposes_its_properties(): void
    {
        $item = new BreadcrumbItem(label: 'Users', url: '/users', icon: 'users');

        $this->assertSame('Users', $item->label);
        $this->assertSame('/users', $item->url);
        $this->assertSame('users', $item->icon);
    }

    public function test_url_and_icon_default_to_null(): void
    {
        $item = new BreadcrumbItem('Dashboard');

        $this->assertNull($item->url);
        $this->assertNull($item->icon);
    }
}
