<?php

namespace FernandoGuiao\StatelessTenancy\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

class TokenExpiredException extends Exception
{
    public function render($request): JsonResponse
    {
        return response()->json([
            'message' => 'Token has expired',
            'error' => 'token_expired'
        ], 401);
    }
}
