<?php

declare(strict_types=1);

namespace Igniter\Storefront\Http\Middleware;

use Closure;
use Igniter\User\Facades\Auth;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectIfCustomer
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            return redirect()->route('igniter.theme.account.account');
        }

        return $next($request);
    }
}
