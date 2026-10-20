<?php

declare(strict_types=1);

namespace Igniter\Storefront\Http\Middleware;

use Closure;
use Igniter\Main\Classes\MainController;
use Igniter\Main\Classes\ThemeManager;
use Igniter\Main\Template\Page;
use Igniter\Storefront\Storefront;
use Igniter\Storefront\Support\GroupOrders;
use Igniter\Storefront\Support\PageMeta;
use Igniter\Storefront\Support\StorefrontRoutes;
use Igniter\Storefront\Support\StorefrontState;
use Igniter\Storefront\Support\ThemeContent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Middleware;
use Override;
use ReflectionProperty;

class HandleInertiaRequests extends Middleware
{
    #[Override]
    public function rootView(Request $request): string
    {
        return Storefront::config()->rootView;
    }

    #[Override]
    public function version(Request $request): ?string
    {
        $code = resolve(ThemeManager::class)->getActiveThemeCode() ?: Storefront::config()->theme;
        $manifest = public_path('vendor/'.$code.'/manifest.json');

        return is_file($manifest) ? hash_file('xxh128', $manifest) : null;
    }

    #[Override]
    public function handle(Request $request, Closure $next)
    {
        $response = parent::handle($request, $next);

        if ($response->headers->get('X-Inertia') === 'true' || $response instanceof JsonResponse) {
            $request->attributes->set(PreserveInertiaPayload::ATTRIBUTE, $response->getContent());
        }

        return $response;
    }

    #[Override]
    public function share(Request $request): array
    {
        $this->bindThemePage();

        return array_merge(parent::share($request), [
            'flash' => [
                'success' => fn() => $request->session()->get('success') ?? $this->extensionFlash(['success', 'info']),
                'error' => fn() => $request->session()->get('error') ?? $this->extensionFlash(['warning', 'danger', 'error', 'alert']),
            ],
            'storefront' => StorefrontState::shared(...),
            'site' => ThemeContent::site(...),
            'routes' => StorefrontRoutes::templates(...),
            // Pages with their own context (an order, a static page) pass a `meta` prop instead.
            'meta' => fn(): array => PageMeta::forRoute($request->route()?->getName()),
            // The active theme's language file. Sent once per visit.
            'lang' => Inertia::once(fn(): array => array_replace_recursive(
                (array)trans(Storefront::config()->langNamespace.'::default'),
                // The group order extension's own strings, so they stay editable in its language group.
                GroupOrders::installed() ? ['grouporder' => (array)trans('igniterlabs.grouporder::default')] : [],
            )),
        ]);
    }

    /**
     * Messages extensions flash with TastyIgniter's flash() helper, e.g. a payment
     * gateway reporting a cancelled or failed payment after an offsite redirect.
     */
    protected function extensionFlash(array $levels): ?string
    {
        $message = flash()->messages()->first(fn($message): bool => in_array($message->level, $levels, true));

        return $message ? trim(html_entity_decode(strip_tags((string)$message->message))) : null;
    }

    /**
     * Theme middleware reads the pagic page from the main controller.
     * Storefront routes have no pagic page, so expose an empty one.
     */
    protected function bindThemePage(): void
    {
        $controller = MainController::getController();
        if (!$controller instanceof MainController || $controller->getPage() instanceof Page) {
            return;
        }

        $page = new Page;
        $page->fileName = 'storefront.htm';
        $page->settings = [];

        $property = new ReflectionProperty($controller, 'page');
        $property->setValue($controller, $page);
    }
}
