<?php
use App\Http\Middleware\PharmacyVerifiedMiddleware;
use App\Http\Middleware\RoleMiddleware;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__ . '/../routes/api.php',
        apiPrefix: 'api',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'role'               => RoleMiddleware::class,
            'pharmacy.verified'  => PharmacyVerifiedMiddleware::class,
        ]);

        $middleware->redirectGuestsTo(fn() => null);

        $middleware->api(prepend: [
            \Illuminate\Http\Middleware\HandleCors::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {

        // Authentication — return JSON instead of redirecting to login
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated. Please login first.',
                'code'    => 401,
            ], 401);
        });

        // JWT errors
        $exceptions->render(function (TokenExpiredException $e, Request $request) {
            return response()->json([
                'success' => false,
                'message' => 'Token has expired. Please login again.',
                'code'    => 401,
            ], 401);
        });

        $exceptions->render(function (TokenInvalidException $e, Request $request) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid token.',
                'code'    => 401,
            ], 401);
        });

        $exceptions->render(function (JWTException $e, Request $request) {
            return response()->json([
                'success' => false,
                'message' => 'Token not provided.',
                'code'    => 401,
            ], 401);
        });

        // 404
        $exceptions->render(function (\Illuminate\Database\Eloquent\ModelNotFoundException $e, Request $request) {
            $model = class_basename($e->getModel());
            return response()->json([
                'success' => false,
                'message' => "{$model} not found.",
                'code'    => 404,
            ], 404);
        });

        // Validation
        $exceptions->render(function (\Illuminate\Validation\ValidationException $e, Request $request) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors'  => $e->errors(),
                'code'    => 422,
            ], 422);
        });
    })
    ->create();