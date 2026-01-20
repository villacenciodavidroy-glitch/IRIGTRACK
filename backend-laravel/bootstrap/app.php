<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Auth\AuthenticationException;
use Symfony\Component\Routing\Exception\RouteNotFoundException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Enable CORS for API routes
        $middleware->api(prepend: [
            \Illuminate\Http\Middleware\HandleCors::class,
        ]);
        
        // Register custom middleware aliases
        $middleware->alias([
            'admin' => \App\Http\Middleware\EnsureUserIsAdmin::class,
            'admin_or_supply' => \App\Http\Middleware\EnsureUserIsAdminOrSupply::class,
        ]);
        
        // Configure authentication redirect for API requests
        // For API requests, return null to prevent redirect and let exception handler catch AuthenticationException
        $middleware->redirectGuestsTo(function ($request) {
            // For API requests, return null to prevent redirect attempt
            // This will cause the Authenticate middleware to throw AuthenticationException
            if ($request->expectsJson() || $request->is('api/*') || str_starts_with($request->path(), 'api/')) {
                return null; // Return null to prevent redirect, will throw AuthenticationException
            }
            // For web requests, return null (or define a login route if needed)
            return null;
        });
    })
    
    ->withExceptions(function (Exceptions $exceptions) {
        // Handle unauthenticated requests for API
        $exceptions->render(function (AuthenticationException $e, $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated. Please login.'
                ], 401);
            }
        });
        
        // Also handle RouteNotFoundException that occurs when trying to redirect to non-existent login route
        $exceptions->render(function (RouteNotFoundException $e, $request) {
            // If this is an API request and the error is about 'login' route, return JSON response
            if (($request->expectsJson() || $request->is('api/*')) && str_contains($e->getMessage(), 'login')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated. Please login.'
                ], 401);
            }
        });
    })->create();
