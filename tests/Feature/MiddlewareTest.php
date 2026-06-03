<?php

namespace Akindutire\Authorization\Tests\Feature;

use Akindutire\Authorization\Exceptions\ValidateSubjectActionException;
use Akindutire\Authorization\Middleware\ValidateSubjectAction;
use Akindutire\Authorization\Tests\Fixtures\TestController;
use Akindutire\Authorization\Tests\Fixtures\TestTeamMember;
use Akindutire\Authorization\Tests\Fixtures\TestUser;
use Akindutire\Authorization\Tests\TestCase;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;

class MiddlewareTest extends TestCase
{
    protected ValidateSubjectAction $middleware;

    protected function setUp(): void
    {
        parent::setUp();
        $this->middleware = new ValidateSubjectAction();
    }

    /** @test */
    public function it_allows_request_when_user_has_required_permission()
    {
        $user = TestUser::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'allowed_permissions' => ['can_view', 'can_edit'],
        ]);

        RouteFacade::get('/test', [TestController::class, 'viewAction']);

        $request = Request::create('/test', 'GET', ['user_id' => $user->id]);
        $request->setRouteResolver(function () {
            return RouteFacade::getRoutes()->match(
                Request::create('/test', 'GET')
            );
        });

        $response = $this->middleware->handle($request, function ($req) {
            return response()->json(['success' => true]);
        });

        $this->assertEquals(200, $response->getStatusCode());
    }

    /** @test */
    public function it_denies_request_when_user_lacks_required_permission()
    {
        $user = TestUser::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'allowed_permissions' => ['can_edit'],
        ]);

        RouteFacade::get('/test', [TestController::class, 'viewAction']);

        $request = Request::create('/test', 'GET', ['user_id' => $user->id]);
        $request->setRouteResolver(function () {
            return RouteFacade::getRoutes()->match(
                Request::create('/test', 'GET')
            );
        });

        $this->expectException(ValidateSubjectActionException::class);
        $this->expectExceptionMessage('Access denied');

        $this->middleware->handle($request, function ($req) {
            return response()->json(['success' => true]);
        });
    }

    /** @test */
    public function it_allows_request_when_user_has_all_required_permissions()
    {
        $user = TestUser::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'allowed_permissions' => ['can_view', 'can_edit', 'can_delete'],
        ]);

        RouteFacade::get('/test', [TestController::class, 'destructiveAction']);

        $request = Request::create('/test', 'GET', ['user_id' => $user->id]);
        $request->setRouteResolver(function () {
            return RouteFacade::getRoutes()->match(
                Request::create('/test', 'GET')
            );
        });

        $response = $this->middleware->handle($request, function ($req) {
            return response()->json(['success' => true]);
        });

        $this->assertEquals(200, $response->getStatusCode());
    }

    /** @test */
    public function it_denies_request_when_user_missing_one_required_permission()
    {
        $user = TestUser::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'allowed_permissions' => ['can_edit'], // missing can_delete
        ]);

        RouteFacade::get('/test', [TestController::class, 'destructiveAction']);

        $request = Request::create('/test', 'GET', ['user_id' => $user->id]);
        $request->setRouteResolver(function () {
            return RouteFacade::getRoutes()->match(
                Request::create('/test', 'GET')
            );
        });

        $this->expectException(ValidateSubjectActionException::class);

        $this->middleware->handle($request, function ($req) {
            return response()->json(['success' => true]);
        });
    }

    /** @test */
    public function it_respects_revoked_permissions()
    {
        $user = TestUser::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'allowed_permissions' => ['can_view', 'can_edit'],
            'revoked_permissions' => ['can_view'],
        ]);

        RouteFacade::get('/test', [TestController::class, 'viewAction']);

        $request = Request::create('/test', 'GET', ['user_id' => $user->id]);
        $request->setRouteResolver(function () {
            return RouteFacade::getRoutes()->match(
                Request::create('/test', 'GET')
            );
        });

        $this->expectException(ValidateSubjectActionException::class);

        $this->middleware->handle($request, function ($req) {
            return response()->json(['success' => true]);
        });
    }

    /** @test */
    public function it_allows_unprotected_routes()
    {
        RouteFacade::get('/test', [TestController::class, 'unprotectedAction']);

        $request = Request::create('/test', 'GET');
        $request->setRouteResolver(function () {
            return RouteFacade::getRoutes()->match(
                Request::create('/test', 'GET')
            );
        });

        $response = $this->middleware->handle($request, function ($req) {
            return response()->json(['success' => true]);
        });

        $this->assertEquals(200, $response->getStatusCode());
    }

    /** @test */
    public function it_works_with_different_models()
    {
        $member = TestTeamMember::create([
            'user_id' => 1,
            'role' => 'admin',
            'allowed_permissions' => ['can_update', 'can_delete'],
        ]);

        RouteFacade::get('/test', [TestController::class, 'teamAction']);

        $request = Request::create('/test', 'GET', ['member_id' => $member->id]);
        $request->setRouteResolver(function () {
            return RouteFacade::getRoutes()->match(
                Request::create('/test', 'GET')
            );
        });

        $response = $this->middleware->handle($request, function ($req) {
            return response()->json(['success' => true]);
        });

        $this->assertEquals(200, $response->getStatusCode());
    }

    /** @test */
    public function it_denies_when_subject_not_found()
    {
        RouteFacade::get('/test', [TestController::class, 'viewAction']);

        $request = Request::create('/test', 'GET', ['user_id' => 999]);
        $request->setRouteResolver(function () {
            return RouteFacade::getRoutes()->match(
                Request::create('/test', 'GET')
            );
        });

        $this->expectException(ValidateSubjectActionException::class);

        $this->middleware->handle($request, function ($req) {
            return response()->json(['success' => true]);
        });
    }

    /** @test */
    public function it_handles_closure_routes_gracefully()
    {
        $request = Request::create('/test', 'GET');
        $request->setRouteResolver(function () {
            $route = new Route('GET', '/test', function () {});
            return $route;
        });

        $response = $this->middleware->handle($request, function ($req) {
            return response()->json(['success' => true]);
        });

        $this->assertEquals(200, $response->getStatusCode());
    }

    /** @test */
    public function it_throws_exception_when_subject_value_is_null()
    {
        RouteFacade::get('/test', [TestController::class, 'viewAction']);

        // Request without user_id
        $request = Request::create('/test', 'GET', []);
        $request->setRouteResolver(function () {
            return RouteFacade::getRoutes()->match(
                Request::create('/test', 'GET')
            );
        });

        $this->expectException(ValidateSubjectActionException::class);
        $this->expectExceptionMessage('Subject value not found');

        $this->middleware->handle($request, function ($req) {
            return response()->json(['success' => true]);
        });
    }

    /** @test */
    public function it_extracts_value_from_route_parameters()
    {
        $user = TestUser::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'allowed_permissions' => ['can_view', 'can_edit'],
        ]);

        RouteFacade::get('/test/{user_id}', [TestController::class, 'viewAction']);

        $request = Request::create("/test/{$user->id}", 'GET');
        $request->setRouteResolver(function () use ($user) {
            $route = RouteFacade::getRoutes()->match(
                Request::create("/test/{$user->id}", 'GET')
            );
            return $route;
        });

        $response = $this->middleware->handle($request, function ($req) {
            return response()->json(['success' => true]);
        });

        $this->assertEquals(200, $response->getStatusCode());
    }

    /** @test */
    public function it_extracts_value_from_query_parameters()
    {
        $user = TestUser::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'allowed_permissions' => ['can_view', 'can_edit'],
        ]);

        RouteFacade::get('/test', [TestController::class, 'viewAction']);

        $request = Request::create("/test?user_id={$user->id}", 'GET');
        $request->setRouteResolver(function () {
            return RouteFacade::getRoutes()->match(
                Request::create('/test', 'GET')
            );
        });

        $response = $this->middleware->handle($request, function ($req) {
            return response()->json(['success' => true]);
        });

        $this->assertEquals(200, $response->getStatusCode());
    }

    /** @test */
    public function it_caches_reflection_metadata()
    {
        $user = TestUser::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'allowed_permissions' => ['can_view', 'can_edit'],
        ]);

        RouteFacade::get('/test', [TestController::class, 'viewAction']);

        $request = Request::create('/test', 'GET', ['user_id' => $user->id]);
        $request->setRouteResolver(function () {
            return RouteFacade::getRoutes()->match(
                Request::create('/test', 'GET')
            );
        });

        // Clear cache before first request
        \Cache::flush();

        // First request - should cache metadata
        $this->middleware->handle($request, function ($req) {
            return response()->json(['success' => true]);
        });

        // Verify cache was created
        $cacheKey = $this->getCacheKeyForController(TestController::class, 'viewAction');
        $this->assertTrue(\Cache::has($cacheKey));

        // Second request - should use cached metadata
        $cachedMetadata = \Cache::get($cacheKey);
        $this->assertIsArray($cachedMetadata);
        $this->assertArrayHasKey('attribute_class', $cachedMetadata);
        $this->assertArrayHasKey('attribute_args', $cachedMetadata);
        $this->assertArrayHasKey('subject_value_key', $cachedMetadata);
    }

    /** @test */
    public function it_uses_auto_invalidation_hash_in_cache_key()
    {
        config(['akindutire-authorization.auto_invalidate_reflection_cache' => true]);

        $cacheKey = $this->getCacheKeyForController(TestController::class, 'viewAction');

        // With auto-invalidation, key should contain hash (8 character hex)
        $this->assertMatchesRegularExpression('/\.[a-f0-9]{8}$/', $cacheKey);
    }

    /** @test */
    public function it_generates_consistent_cache_keys_for_same_controller_method()
    {
        $key1 = $this->getCacheKeyForController(TestController::class, 'viewAction');
        $key2 = $this->getCacheKeyForController(TestController::class, 'viewAction');

        $this->assertEquals($key1, $key2);
    }

    /** @test */
    public function it_generates_different_cache_keys_for_different_methods()
    {
        $key1 = $this->getCacheKeyForController(TestController::class, 'viewAction');
        $key2 = $this->getCacheKeyForController(TestController::class, 'destructiveAction');

        $this->assertNotEquals($key1, $key2);
    }

    /** @test */
    public function it_handles_json_array_permissions_correctly()
    {
        // User with permissions stored as JSON array (auto-cast by trait)
        $user = TestUser::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'allowed_permissions' => ['can_view', 'can_edit', 'can_delete'],
        ]);

        // Verify database storage is JSON
        $rawValue = \DB::table('test_users')
            ->where('id', $user->id)
            ->value('allowed_permissions');

        $this->assertJson($rawValue);

        // Verify middleware can read and validate
        RouteFacade::get('/test', [TestController::class, 'viewAction']);

        $request = Request::create('/test', 'GET', ['user_id' => $user->id]);
        $request->setRouteResolver(function () {
            return RouteFacade::getRoutes()->match(
                Request::create('/test', 'GET')
            );
        });

        $response = $this->middleware->handle($request, function ($req) {
            return response()->json(['success' => true]);
        });

        $this->assertEquals(200, $response->getStatusCode());
    }

    /**
     * Helper to generate cache key for a controller method
     * Uses the same logic as ReflectionCacheKeyGenerator
     */
    private function getCacheKeyForController(string $controller, string $method): string
    {
        return \Akindutire\Authorization\Support\ReflectionCacheKeyGenerator::generate($controller, $method);
    }
}
