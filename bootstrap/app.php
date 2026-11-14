<?php
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\EnsureUserHasPermission;
use App\Http\Middleware\SecurityHeaders;
return Application::configure(basePath: dirname(__DIR__))
  ->withRouting(web: __DIR__.'/../routes/web.php', api: __DIR__.'/../routes/api.php', commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php', health: '/up')
  ->withMiddleware(function (Middleware $middleware) {
      $middleware->statefulApi();
      $middleware->alias(['role' => EnsureUserHasRole::class, 'permission' => EnsureUserHasPermission::class]);
      $middleware->appendToGroup('web', SecurityHeaders::class);
      $middleware->appendToGroup('api', SecurityHeaders::class);
  })
  ->withExceptions(function (Exceptions $exceptions) {
      $exceptions->render(function (ValidationException $exception, Request $request) {
          if (!$request->expectsJson()) return null;
          return response()->json([
              'message' => 'Please review the information below and correct the highlighted fields.',
              'errors' => $exception->errors(),
          ], 422);
      });
      $exceptions->render(function (ThrottleRequestsException $exception, Request $request) {
          if (!$request->expectsJson()) return null;
          return response()->json(['message' => 'Too many attempts. Please wait a little while, then try again.'], 429, $exception->getHeaders());
      });
  })
  ->create();
