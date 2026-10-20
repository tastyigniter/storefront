<?php

declare(strict_types=1);

namespace Igniter\Storefront\Http\Middleware;

use Closure;
use Igniter\Flame\Support\Facades\Igniter;
use Igniter\Storefront\Http\Controllers\PageController;
use Igniter\Storefront\Support\ThemeGate;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * igniter.pages registers a route per static page that renders a Blade layout.
 * The storefront has no Blade layouts, so render those pages through Inertia instead.
 */
class RenderStaticPages
{
    public function handle(Request $request, Closure $next): Response
    {
        $name = (string)$request->route()?->getName();
        if (!str_starts_with($name, 'igniter.pages.') || !ThemeGate::active() || Igniter::runningInAdmin()) {
            return $next($request);
        }

        $slug = Str::after($name, 'igniter.pages.');

        return resolve(HandleInertiaRequests::class)->handle(
            $request,
            fn(Request $request): Response => resolve(PageController::class)->show($slug)->toResponse($request),
        );
    }
}
