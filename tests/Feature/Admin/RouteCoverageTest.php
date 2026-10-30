<?php

use App\Models\ClergyAssignment;
use App\Models\Community;
use App\Models\Family;
use App\Models\Member;
use App\Models\ProfileChangeRequest;
use App\Models\Role;
use App\Models\User;
use App\Models\UserInvitation;
use Illuminate\Routing\Route as RouteDefinition;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Permission as PermissionModel;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

test('every admin route requires its own permission', function () {
    // Routes any admin user may open without a module permission.
    $openRoutes = ['admin.dashboard'];

    PermissionModel::findOrCreate('testing.unrelated');
    $user = User::factory()->create();
    $user->givePermissionTo('testing.unrelated');

    $parameters = [
        'role' => Role::create(['name' => 'Choir Coordinator'])->id,
        'user' => User::factory()->create()->id,
        'invitation' => UserInvitation::factory()->create()->id,
        'member' => $member = Member::factory()->inFamily($family = Family::factory()->create())->create(),
        'family' => $family->id,
        'community' => Community::factory()->create()->id,
        'clergy' => ClergyAssignment::factory()->create()->id,
        'changeRequest' => ProfileChangeRequest::factory()->for($member)->create()->id,
    ];
    $parameters['member'] = $member->id;

    $routes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn (RouteDefinition $route) => str_starts_with((string) $route->getName(), 'admin.'))
        ->reject(fn (RouteDefinition $route) => in_array($route->getName(), $openRoutes, true));

    expect($routes)->not->toBeEmpty();

    $this->actingAs($user);

    foreach ($routes as $route) {
        $method = collect($route->methods())->reject(fn (string $method) => $method === 'HEAD')->first();
        $url = route($route->getName(), array_intersect_key($parameters, array_flip($route->parameterNames())));

        expect($this->call($method, $url)->status())
            ->toBe(403, "{$method} /{$route->uri()} ({$route->getName()}) is reachable without its permission.");
    }
});

test('the admin area is closed to users without any admin permission', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('admin.dashboard'))
        ->assertForbidden();
});

test('only the user model checks roles by name', function () {
    $offenders = collect(Finder::create()->files()->name('*.php')->in([app_path(), base_path('routes')]))
        ->reject(fn (SplFileInfo $file) => $file->getRealPath() === app_path('Models/User.php'))
        ->filter(fn (SplFileInfo $file) => preg_match('/->has(Any|All|Exact)?Roles?\(|[\'"]role(_or_permission)?:/', $file->getContents()) === 1)
        ->map(fn (SplFileInfo $file) => $file->getRelativePathname())
        ->values()
        ->all();

    expect($offenders)->toBe([], 'Check permissions instead of role names in: '.implode(', ', $offenders));
});
