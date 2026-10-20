<?php

declare(strict_types=1);

namespace Igniter\Storefront\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Runs outside other `igniter` middleware so JSON (Inertia pages and theme endpoints)
 * reaches the browser untouched. The impersonation banner middleware appends HTML to
 * responses without a </body> tag, which corrupts JSON.
 */
class PreserveInertiaPayload
{
    public const string ATTRIBUTE = 'storefront.inertia-payload';

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $payload = $request->attributes->get(self::ATTRIBUTE);
        if (is_string($payload) && $response->getContent() !== $payload) {
            $response->setContent($payload);
        }

        return $response;
    }
}
