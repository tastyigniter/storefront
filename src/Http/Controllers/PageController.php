<?php

declare(strict_types=1);

namespace Igniter\Storefront\Http\Controllers;

use Igniter\Pages\Models\Page;
use Igniter\Storefront\Http\Concerns\ResolvesStorefrontPage;
use Igniter\Storefront\Support\PageMeta;
use Igniter\Storefront\Support\ThemeContent;
use Inertia\Inertia;
use Inertia\Response;
use Stevebauman\Purify\Facades\Purify;

/**
 * Renders static pages from Design > Static Pages (igniter.pages).
 */
class PageController
{
    use ResolvesStorefrontPage;

    public function show(string $slug): Response
    {
        $page = Page::query()->where('permalink_slug', $slug)->where('status', true)->firstOrFail();
        assert($page instanceof Page);

        // The content accessor can come back empty (e.g. a missing translation); use the stored copy then.
        $content = (string)$page->content ?: html_entity_decode((string)$page->getRawOriginal('content'));

        return Inertia::render('Pages/Show', [
            'page' => [
                'title' => (string)$page->title,
                'content' => Purify::clean($content),
            ],
            'pagesMenu' => ThemeContent::menu('pages-menu'),
            // Static pages have their own meta description; otherwise describe them from their content.
            'meta' => PageMeta::make((string)$page->title, (string)$page->meta_description ?: $content),
        ]);
    }
}
