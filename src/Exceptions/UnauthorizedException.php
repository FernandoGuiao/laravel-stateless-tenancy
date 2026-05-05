<?php

namespace FernandoGuiao\StatelessTenancy\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

class UnauthorizedException extends Exception
{
    public function render($request): JsonResponse
    {
        return response()->json([
            'message' => 'Unauthorized or invalid token',
            'error' => 'unauthorized'
        ], 401);
    }
}
