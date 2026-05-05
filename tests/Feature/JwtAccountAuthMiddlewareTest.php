<?php

namespace FernandoGuiao\StatelessTenancy\Tests\Feature;

use FernandoGuiao\StatelessTenancy\Exceptions\UnauthorizedException;
use FernandoGuiao\StatelessTenancy\Http\Middleware\JwtAccountAuth;
use FernandoGuiao\StatelessTenancy\Services\AuthService;
use FernandoGuiao\StatelessTenancy\Tests\Fixtures\Account;
use FernandoGuiao\StatelessTenancy\Tests\Fixtures\User;
use FernandoGuiao\StatelessTenancy\Tests\TestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class JwtAccountAuthMiddlewareTest extends TestCase
{
    public function test_it_throws_unauthorized_if_no_token()
    {
        $middleware = new JwtAccountAuth();
        $request = new Request();

        $this->expectException(UnauthorizedException::class);
        $middleware->handle($request, function () {});
    }

    public function test_it_throws_unauthorized_if_invalid_token()
    {
        $middleware = new JwtAccountAuth();
        $request = new Request();
        $request->headers->set('Authorization', 'Bearer invalid_token');

        $this->expectException(UnauthorizedException::class);
        $middleware->handle($request, function () {});
    }

    public function test_it_allows_valid_token_and_binds_service()
    {
        $user = User::create(['name' => 'John', 'email' => 'john@test.com', 'password' => Hash::make('password')]);
        $account = Account::create(['name' => 'Johns Company']);
        $user->accounts()->attach($account->id, ['role_id' => 1]); // dummy role

        $authService = new AuthService();
        $token = $authService->issueToken($user, false, $account->id);

        $middleware = new JwtAccountAuth();
        $request = new Request();
        $request->headers->set('Authorization', 'Bearer ' . $token);

        $called = false;
        $middleware->handle($request, function ($req) use (&$called) {
            $called = true;
            return response('ok');
        });

        $this->assertTrue($called);
        $this->assertTrue(app()->bound(AuthService::class));
        $this->assertEquals($account->id, app(AuthService::class)->getAccountId());
    }
}
