<?php

namespace FernandoGuiao\StatelessTenancy\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

class RefreshTokenExpiredException extends Exception
{
    public function render($request): JsonResponse
    {
        return response()->json([
            'message' => 'Refresh Token has expired',
            'error' => 'refresh_token_expired'
        ], 401);
    }
}
