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
            'allowed_permissions' => 'can_view,can_edit',
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
            'allowed_permissions' => 'can_edit',
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
            'allowed_permissions' => 'can_view,can_edit,can_delete',
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
            'allowed_permissions' => 'can_edit', // missing can_delete
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
            'allowed_permissions' => 'can_view,can_edit',
            'revoked_permissions' => 'can_view',
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
            'allowed_permissions' => 'can_update,can_delete',
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
            'allowed_permissions' => 'can_view,can_edit',
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
            'allowed_permissions' => 'can_view,can_edit',
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
}
