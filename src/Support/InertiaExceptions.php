<?php

declare(strict_types=1);

namespace Igniter\Storefront\Support;

use Igniter\Flame\Exception\ApplicationException;
use Igniter\Flame\Exception\FlashException;
use Igniter\Storefront\Storefront;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Turn storefront exceptions into a redirect back with a flash error for Inertia visits.
 */
final class InertiaExceptions
{
    public static function register(Application $app): void
    {
        if (!$app->bound(ExceptionHandler::class)) {
            return;
        }

        /** @var ExceptionHandler $handler */
        $handler = $app->make(ExceptionHandler::class);

        $handler->renderable(function(FlashException $exception, Request $request) {
            if (!self::inertiaStorefront($request)) {
                return null;
            }

            return redirect()->back(fallback: self::fallbackUrl())->with('error', $exception->getMessage());
        });

        $handler->renderable(function(ApplicationException $exception, Request $request) {
            if (!self::inertiaStorefront($request)) {
                return null;
            }

            return redirect()->back(fallback: self::fallbackUrl())->with('error', $exception->getMessage());
        });

        $handler->renderable(function(Throwable $exception, Request $request) {
            if (!self::inertiaStorefront($request)) {
                return null;
            }

            if ($exception instanceof ValidationException) {
                return null;
            }

            if ($exception instanceof HttpExceptionInterface && $exception->getStatusCode() < 500) {
                return null;
            }

            report($exception);

            return redirect()->back(fallback: self::fallbackUrl())->with('error', Storefront::lang('alert.something_wrong'));
        });
    }

    protected static function inertiaStorefront(Request $request): bool
    {
        return $request->header('X-Inertia') === 'true' && ThemeGate::active();
    }

    protected static function fallbackUrl(): string
    {
        return route('igniter.theme.home', [], false);
    }
}
