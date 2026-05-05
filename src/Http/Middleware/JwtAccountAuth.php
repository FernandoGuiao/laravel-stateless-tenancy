<?php

namespace FernandoGuiao\StatelessTenancy\Http\Middleware;

use Closure;
use FernandoGuiao\StatelessTenancy\Exceptions\UnauthorizedException;
use FernandoGuiao\StatelessTenancy\Services\AuthService;
use Illuminate\Http\Request;

class JwtAccountAuth
{
    public function handle(Request $request, Closure $next)
    {
        $token = $request->bearerToken();

        if (!$token) {
            throw new UnauthorizedException();
        }

        $authService = new AuthService($token);

        if (!$authService->validate()) {
            throw new UnauthorizedException(); // Validations internally throw Expired exceptions when needed
        }

        // Bind the validated AuthService to the container so traits can use it
        app()->instance(AuthService::class, $authService);

        return $next($request);
    }
}
