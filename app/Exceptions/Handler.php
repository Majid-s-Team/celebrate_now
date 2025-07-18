<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Auth\AuthenticationException;  // <-- import this    
use Throwable;

class Handler extends ExceptionHandler
{
    
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Handle unauthenticated exceptions (e.g. Sanctum token errors)
     * This method is called when an unauthenticated user tries to access a route that requires authentication.
     * It returns a JSON response with a 401 status code and an error message according to the API standards.
     *
     *
     */
   protected function unauthenticated($request, AuthenticationException $exception)
{
    return response()->json([
        'message' => 'Authentication failed: Invalid or missing token.',
        'error' => 'Invalid or missing token',
        'status_code' => 401,
        'data' => [],
    ], 401);
}

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }
}
