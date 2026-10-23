<?php

declare(strict_types=1);

namespace Igniter\Storefront;

use Igniter\Flame\Pagic\Router as PagicRouter;
use Igniter\PayRegister\Models\PaymentProfile;
use Igniter\Storefront\Http\Middleware\PreserveInertiaPayload;
use Igniter\Storefront\Http\Middleware\RenderStaticPages;
use Igniter\Storefront\Http\RouteRegistrar;
use Igniter\Storefront\Support\InertiaExceptions;
use Igniter\Storefront\Support\PageRouter;
use Igniter\Storefront\Support\ThemeGate;
use Igniter\User\Models\Customer;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;
use Illuminate\View\View as ViewInstance;
use Override;

class ServiceProvider extends BaseServiceProvider
{
    #[Override]
    public function register(): void
    {
        // Let page_url() resolve theme page names to the storefront routes.
        $this->app->extend(PagicRouter::class, fn(PagicRouter $router): PageRouter => new PageRouter);
    }

    public function boot(): void
    {
        InertiaExceptions::register($this->app);

        // Static pages render through Inertia; the JSON guard wraps everything, including the
        // impersonation banner middleware, so it cannot append HTML to Inertia JSON.
        $this->app['router']->prependMiddlewareToGroup('igniter', RenderStaticPages::class);
        $this->app['router']->prependMiddlewareToGroup('igniter', PreserveInertiaPayload::class);

        // Saved cards belong to igniter.payregister; drop them when a customer deletes their account.
        Event::listen('igniter.user.beforeDeleteAccount', function(Customer $customer): void {
            if (class_exists(PaymentProfile::class)) {
                PaymentProfile::query()->where('customer_id', $customer->getKey())->delete();
            }
        });

        Event::listen('igniter.main.registerRoutes', function(Router $router): void {
            if (!ThemeGate::active()) {
                return;
            }

            (new RouteRegistrar)->register($router);
        });

        foreach (Storefront::themes() as $config) {
            // Orange shares `page` onto every public view, which replaces the Inertia payload.
            // Keep the payload aside, then put it back after that composer has run.
            View::composer($config->rootView, function(ViewInstance $view): void {
                $page = $view->getData()['page'] ?? null;
                if (is_array($page) && isset($page['component'])) {
                    $view->with('inertiaPage', $page);
                }
            });
        }

        $this->app->booted(function(): void {
            $rootViews = array_map(
                fn(Config $config): string => $config->rootView,
                Storefront::themes(),
            );

            View::composer('*', function(ViewInstance $view) use ($rootViews): void {
                if (!in_array($view->name(), $rootViews, true)) {
                    return;
                }

                $page = $view->getData()['inertiaPage'] ?? null;
                if (is_array($page)) {
                    $view->with('page', $page);
                }
            });
        });
    }
}
