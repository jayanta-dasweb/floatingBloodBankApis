<?php

namespace App\Http\Middleware;

use Closure;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Illuminate\Support\Facades\Log;

class ValidateJwtToken
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        try {
            // Check if token is present
            $token = JWTAuth::parseToken();

            // Validate the token
            $user = $token->authenticate();

            // If authentication fails
            if (!$user) {
                return response()->json(apiResponse(null, 'Unauthorized', 401, ['User not found'], 'auth'));
            }

            // Pass the request to the next middleware/controller
            return $next($request);
        } catch (TokenExpiredException $e) {
            Log::error('JWT Token Expired: ' . $e->getMessage());
            return response()->json(apiResponse(null, 'Token has expired', 401, [$e->getMessage()], 'auth'));
        } catch (TokenInvalidException $e) {
            Log::error('Invalid JWT Token: ' . $e->getMessage());
            return response()->json(apiResponse(null, 'Invalid token', 401, [$e->getMessage()], 'auth'));
        } catch (JWTException $e) {
            Log::error('JWT Error: ' . $e->getMessage());
            return response()->json(apiResponse(null, 'Token not provided', 401, [$e->getMessage()], 'auth'));
        } catch (\Exception $e) {
            Log::error('JWT Middleware Error: ' . $e->getMessage());
            return response()->json(apiResponse(null, 'Server error', 500, [$e->getMessage()], 'server'));
        }
    }
}
