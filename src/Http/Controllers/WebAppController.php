<?php

declare(strict_types=1);

namespace Igniter\Storefront\Http\Controllers;

use Igniter\Storefront\Storefront;
use Igniter\Storefront\Support\ThemeColors;
use Igniter\Storefront\Support\ThemeContent;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Install metadata for the storefront. The manifest follows the restaurant's
 * theme settings. The worker and offline page are served here so a copy under
 * the published theme directory is not required.
 */
class WebAppController
{
    public function manifest(): JsonResponse
    {
        $name = trim((string)setting('site_name', ''));
        if ($name === '') {
            $name = Storefront::lang('site.name');
        }

        $color = ThemeColors::background();

        return response()->json([
            'name' => $name,
            'short_name' => mb_strlen($name) > 12 ? mb_substr($name, 0, 12) : $name,
            'start_url' => '/',
            'scope' => '/',
            'display' => 'standalone',
            'background_color' => $color,
            'theme_color' => $color,
            'icons' => $this->icons(),
        ])->header('Content-Type', 'application/manifest+json');
    }

    public function serviceWorker(): Response
    {
        return $this->file('sw.js', 'application/javascript; charset=UTF-8', [
            'Service-Worker-Allowed' => '/',
        ]);
    }

    public function offline(): Response
    {
        $config = Storefront::config();
        $path = $config->pwaDirectory.'/offline.html';
        abort_unless(is_file($path), 404);

        $html = str_replace(
            [strtolower($config->defaultBackground), strtolower($config->defaultRing)],
            [ThemeColors::background(), ThemeColors::ring()],
            (string)file_get_contents($path),
        );

        // A plain response, not Laravel's, so theme middleware does not ask this
        // controller for a pagic page when it sees HTML.
        return new Response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Cache-Control' => 'no-cache',
        ]);
    }

    /**
     * @return list<array{src: string, sizes: string, type: string, purpose: string}>
     */
    protected function icons(): array
    {
        $uploaded = ThemeContent::pwaIcon();
        if (is_string($uploaded)) {
            $type = $this->iconType($uploaded);

            return [
                $this->icon($uploaded, '192x192', 'any', $type),
                $this->icon($uploaded, '512x512', 'any', $type),
                $this->icon($uploaded, '512x512', 'maskable', $type),
            ];
        }

        $base = '/vendor/'.Storefront::config()->theme.'/icons';

        return [
            $this->icon($base.'/icon-192.png', '192x192', 'any', 'image/png'),
            $this->icon($base.'/icon-512.png', '512x512', 'any', 'image/png'),
            $this->icon($base.'/icon-maskable.png', '512x512', 'maskable', 'image/png'),
        ];
    }

    /**
     * @return array{src: string, sizes: string, type: string, purpose: string}
     */
    protected function icon(string $src, string $sizes, string $purpose, string $type): array
    {
        return [
            'src' => $src,
            'sizes' => $sizes,
            'type' => $type,
            'purpose' => $purpose,
        ];
    }

    protected function iconType(string $url): string
    {
        $extension = strtolower(pathinfo($url, PATHINFO_EXTENSION));

        return match ($extension) {
            'jpg', 'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            'gif' => 'image/gif',
            default => 'image/png',
        };
    }

    /**
     * @param array<string, string> $headers
     */
    protected function file(string $name, string $contentType, array $headers = []): Response
    {
        $path = Storefront::config()->pwaDirectory.'/'.$name;
        abort_unless(is_file($path), 404);

        return response((string)file_get_contents($path), 200, [
            'Content-Type' => $contentType,
            'Cache-Control' => 'no-cache',
            ...$headers,
        ]);
    }
}
