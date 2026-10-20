<?php

declare(strict_types=1);

use Igniter\Storefront\Storefront;
use Igniter\Storefront\Support\ThemeFont;

it('uses Plus Jakarta Sans until a Google font is set', function(): void {
    expect(ThemeFont::stylesheet())->toContain('Plus+Jakarta+Sans')
        ->and(ThemeFont::css())->toBeNull();

    setThemeSettings(['font_url' => Storefront::config()->defaultFontUrl]);

    expect(ThemeFont::css())->toBeNull();
});

it('loads the first family from a Google Fonts URL', function(): void {
    currentLocation();
    setThemeSettings([
        'font_url' => 'https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400&family=Source+Sans+3:wght@400;600&display=swap',
    ]);

    expect(ThemeFont::css())->toBe(":root{--font-sans:'Fraunces',ui-sans-serif,system-ui,sans-serif;}")
        ->and(ThemeFont::stylesheet())->toContain('family=Fraunces');

    $this->get(route('igniter.theme.home'))
        ->assertOk()
        ->assertSee('family=Fraunces', false)
        ->assertSee("--font-sans:'Fraunces'", false)
        ->assertDontSee('Plus+Jakarta+Sans', false);
});

it('accepts the older Google Fonts CSS address', function(): void {
    setThemeSettings(['font_url' => 'https://fonts.googleapis.com/css?family=Open+Sans:400,700']);

    expect(ThemeFont::css())->toBe(":root{--font-sans:'Open Sans',ui-sans-serif,system-ui,sans-serif;}");
});

it('ignores a font URL that is not a Google Fonts stylesheet', function(string $url): void {
    setThemeSettings(['font_url' => $url]);

    expect(ThemeFont::stylesheet())->toContain('Plus+Jakarta+Sans')
        ->and(ThemeFont::css())->toBeNull();
})->with([
    'http://fonts.googleapis.com/css2?family=Inter',
    'https://evil.example/css2?family=Inter',
    'https://fonts.googleapis.com.evil/css2?family=Inter',
    'https://user:pass@fonts.googleapis.com/css2?family=Inter',
    'https://fonts.googleapis.com/css2?family=%3Cscript%3E',
    'not a url',
]);
