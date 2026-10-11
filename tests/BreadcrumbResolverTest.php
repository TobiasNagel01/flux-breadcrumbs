<?php

namespace TobiasNagel\FluxBreadcrumbs\Tests;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Stringable;
use LogicException;
use TobiasNagel\FluxBreadcrumbs\BreadcrumbItem;
use TobiasNagel\FluxBreadcrumbs\BreadcrumbResolver;
use TobiasNagel\FluxBreadcrumbs\Tests\Fixtures\Enums\Priority;
use TobiasNagel\FluxBreadcrumbs\Tests\Fixtures\Enums\Status;
use TobiasNagel\FluxBreadcrumbs\Tests\Fixtures\Models\Team;
use TobiasNagel\FluxBreadcrumbs\Tests\Fixtures\Models\User;

class BreadcrumbResolverTest extends TestCase
{
    private BreadcrumbResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resolver = $this->app->make(BreadcrumbResolver::class);
    }

    public function test_it_returns_an_empty_trail_for_a_null_route(): void
    {
        $this->assertSame([], $this->resolver->resolve(null));
    }

    public function test_it_returns_an_empty_trail_for_a_route_without_breadcrumb(): void
    {
        $route = $this->bind($this->route('/plain', 'plain'), '/plain');

        $this->assertSame([], $this->resolver->resolve($route));
    }

    public function test_it_resolves_a_single_route(): void
    {
        $route = $this->route('/dashboard', 'dashboard')->breadcrumb('Dashboard', icon: 'home');

        $items = $this->resolver->resolve($this->bind($route, '/dashboard'));

        $this->assertCount(1, $items);
        $this->assertEquals(new BreadcrumbItem('Dashboard', null, 'home'), $items[0]);
    }

    public function test_it_walks_the_parent_chain_and_links_ancestors(): void
    {
        $this->route('/dashboard', 'dashboard')->breadcrumb('Dashboard');
        $this->route('/users', 'users.index')->breadcrumb('Users', parent: 'dashboard', icon: 'users');
        $show = $this->route('/users/{user}', 'users.show')->breadcrumb('{user.name}', parent: 'users.index');

        $user = new User(['name' => 'Jane Doe']);

        $items = $this->resolver->resolve($this->bind($show, '/users/1', ['user' => $user]));

        $this->assertEquals([
            new BreadcrumbItem('Dashboard', 'http://localhost/dashboard'),
            new BreadcrumbItem('Users', 'http://localhost/users', 'users'),
            new BreadcrumbItem('Jane Doe'),
        ], $items);
    }

    public function test_parent_urls_only_receive_parameters_declared_by_the_parent(): void
    {
        $this->route('/teams/{team}', 'teams.show')->breadcrumb('Team');
        $this->route('/teams/{team}/users', 'teams.users.index')->breadcrumb('Users', parent: 'teams.show');
        $show = $this->route('/teams/{team}/users/{user}', 'teams.users.show')
            ->breadcrumb('User', parent: 'teams.users.index');

        $items = $this->resolver->resolve($this->bind($show, '/teams/7/users/3', ['team' => 7, 'user' => 3]));

        $this->assertSame('http://localhost/teams/7', $items[0]->url);
        $this->assertSame('http://localhost/teams/7/users', $items[1]->url);
        $this->assertNull($items[2]->url);
    }

    public function test_parent_urls_use_url_defaults_for_missing_parameters(): void
    {
        URL::defaults(['locale' => 'de']);

        $this->route('/{locale}/dashboard', 'dashboard')->breadcrumb('Dashboard');
        $about = $this->route('/about', 'about')->breadcrumb('About', parent: 'dashboard');

        $items = $this->resolver->resolve($this->bind($about, '/about'));

        $this->assertSame('http://localhost/de/dashboard', $items[0]->url);
    }

    public function test_it_resolves_placeholders_from_scalar_and_array_parameters(): void
    {
        $route = $this->route('/{a}/{b}', 'scalar')->breadcrumb('{a} - {b.nested.key} - {b.missing}');

        $items = $this->resolver->resolve($this->bind($route, '/x/y', [
            'a' => 42,
            'b' => ['nested' => ['key' => 'deep']],
        ]));

        $this->assertSame('42 - deep - ', $items[0]->label);
    }

    public function test_it_leaves_labels_without_placeholders_untouched(): void
    {
        $route = $this->route('/static', 'static')->breadcrumb('Just {not a placeholder} text');

        $items = $this->resolver->resolve($this->bind($route, '/static'));

        $this->assertSame('Just {not a placeholder} text', $items[0]->label);
    }

    public function test_it_resolves_model_attributes_accessors_and_casts(): void
    {
        $route = $this->route('/users/{user}', 'users.show')
            ->breadcrumb('{user.name}|{user.display_name}|{user.initials}|{user.status}|{user.status.name}|{user.unknown}');

        $user = new User(['name' => 'jane', 'status' => 'active']);

        $items = $this->resolver->resolve($this->bind($route, '/users/1', ['user' => $user]));

        $this->assertSame('jane|Display: jane|J|active|Active|', $items[0]->label);
    }

    public function test_it_resolves_an_already_loaded_relationship_without_querying(): void
    {
        $route = $this->route('/users/{user}', 'users.show')->breadcrumb('{user.team.name}');

        $user = new User(['name' => 'jane']);
        $user->setRelation('team', new Team(['name' => 'Engineering']));

        $items = $this->resolver->resolve($this->bind($route, '/users/1', ['user' => $user]));

        $this->assertSame('Engineering', $items[0]->label);
    }

    public function test_it_eager_loads_an_unloaded_relationship(): void
    {
        $this->migrate();

        $team = Team::query()->create(['name' => 'Engineering']);
        $user = User::query()->create(['name' => 'jane', 'team_id' => $team->id]);

        Model::preventLazyLoading();

        try {
            $route = $this->route('/users/{user}', 'users.show')->breadcrumb('{user.team.name}');

            $items = $this->resolver->resolve($this->bind($route, '/users/1', ['user' => $user->fresh()]));
        } finally {
            Model::preventLazyLoading(false);
        }

        $this->assertSame('Engineering', $items[0]->label);
    }

    public function test_a_null_relationship_resolves_to_an_empty_string(): void
    {
        $this->migrate();

        $user = User::query()->create(['name' => 'jane', 'team_id' => null]);

        $route = $this->route('/users/{user}', 'users.show')->breadcrumb('[{user.team.name}]');

        $items = $this->resolver->resolve($this->bind($route, '/users/1', ['user' => $user->fresh()]));

        $this->assertSame('[]', $items[0]->label);
    }

    public function test_it_stringifies_enums_stringables_scalars_and_other_values(): void
    {
        $route = $this->route('/{p}', 'stringify')
            ->breadcrumb('{backed}|{backed.value}|{unit}|{unit.name}|{stringable}|{int}|{bool}|{float}|{array}|{object}|{null}');

        $items = $this->resolver->resolve($this->bind($route, '/p', [
            'backed' => Status::Archived,
            'unit' => Priority::High,
            'stringable' => new Stringable('str'),
            'int' => 7,
            'bool' => true,
            'float' => 1.5,
            'array' => ['a' => 'b'],
            'object' => new \stdClass,
            'null' => null,
        ]));

        $this->assertSame('archived|archived|High|High|str|7|1|1.5|||', $items[0]->label);
    }

    public function test_it_throws_when_a_parent_route_does_not_exist(): void
    {
        $route = $this->route('/orphan', 'orphan')->breadcrumb('Orphan', parent: 'missing');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Breadcrumb parent route [missing] referenced by [orphan] does not exist.');

        $this->resolver->resolve($this->bind($route, '/orphan'));
    }

    public function test_it_throws_when_a_parent_has_no_breadcrumb_configuration(): void
    {
        $this->route('/parent', 'parent');
        $route = $this->route('/child', 'child')->breadcrumb('Child', parent: 'parent');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Route [parent] has no breadcrumb configuration.');

        $this->resolver->resolve($this->bind($route, '/child'));
    }

    public function test_it_throws_when_the_parent_chain_contains_a_cycle(): void
    {
        $a = $this->route('/a', 'a')->breadcrumb('A', parent: 'b');
        $this->route('/b', 'b')->breadcrumb('B', parent: 'a');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Breadcrumb parent chain contains a cycle at route [a].');

        $this->resolver->resolve($this->bind($a, '/a'));
    }

    public function test_it_detects_a_self_referencing_route(): void
    {
        $route = $this->route('/self', 'self')->breadcrumb('Self', parent: 'self');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('cycle at route [self]');

        $this->resolver->resolve($this->bind($route, '/self'));
    }

    private function migrate(): void
    {
        Schema::create('teams', function ($table): void {
            $table->id();
            $table->string('name');
        });

        Schema::create('users', function ($table): void {
            $table->id();
            $table->string('name');
            $table->string('status')->nullable();
            $table->foreignId('team_id')->nullable();
        });
    }
}
