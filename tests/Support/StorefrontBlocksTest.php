<?php

declare(strict_types=1);

namespace Igniter\Storefront\Tests\Support;

use Igniter\Cart\Models\Menu;
use Igniter\Local\Models\Location;
use Igniter\Main\Classes\BaseBlock;
use Igniter\Main\Classes\BlockManager;
use Igniter\Main\Classes\ThemeManager;
use Igniter\Main\Models\Theme as ThemeModel;
use Igniter\Main\Models\ThemePage;
use Igniter\Storefront\Blocks\AccountNav;
use Igniter\Storefront\Blocks\Banners;
use Igniter\Storefront\Blocks\Breadcrumb;
use Igniter\Storefront\Blocks\CallToAction;
use Igniter\Storefront\Blocks\CategoryList;
use Igniter\Storefront\Blocks\CheckoutForm;
use Igniter\Storefront\Blocks\CheckoutHeader;
use Igniter\Storefront\Blocks\ContactForm;
use Igniter\Storefront\Blocks\FeaturedItems;
use Igniter\Storefront\Blocks\FeaturedLocations;
use Igniter\Storefront\Blocks\Hero;
use Igniter\Storefront\Blocks\Html;
use Igniter\Storefront\Blocks\Image;
use Igniter\Storefront\Blocks\ImageText;
use Igniter\Storefront\Blocks\LocalHeader;
use Igniter\Storefront\Blocks\LocationList;
use Igniter\Storefront\Blocks\LoginForm;
use Igniter\Storefront\Blocks\MenuItemList;
use Igniter\Storefront\Blocks\OrderingFrom;
use Igniter\Storefront\Blocks\OrderOptions;
use Igniter\Storefront\Blocks\OrderSummary;
use Igniter\Storefront\Blocks\PageBlock;
use Igniter\Storefront\Blocks\PageHeader;
use Igniter\Storefront\Blocks\PageView;
use Igniter\Storefront\Blocks\RichText;
use Igniter\Storefront\Blocks\Spacer;
use Igniter\Storefront\Blocks\StorefrontBlocks;
use Igniter\Storefront\Support\PageSections;
use Igniter\Storefront\Support\ThemeContent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Lang;

it('lists the shared storefront blocks', function(): void {
    resolve(BlockManager::class)->registerBlocks('storefront-fixture', StorefrontBlocks::definitions());
    $theme = resolve(ThemeManager::class)->findTheme('storefront-fixture');

    $codes = array_keys(resolve(BlockManager::class)->listBlocks($theme));

    expect($codes)->toContain('heading', 'rich-text', 'image', 'image-text', 'call-to-action', 'html', 'spacer', 'hero', 'banners', 'featured-items')
        ->and($codes)->toContain(...array_column(StorefrontBlocks::PAGE_VIEWS, 0))
        ->and(array_keys(resolve(BlockManager::class)->listBlocks($theme, 'checkout.checkout')))->toContain('checkout-form')->not->toContain('login-form');
});

it('builds page view blocks limited to their page', function(): void {
    $block = PageView::make('checkout-form', 'checkout.checkout', 'Checkout', 'fa fa-cash-register');

    expect($block->getDetails())->toMatchArray([
        'code' => 'checkout-form',
        'unique' => true,
        'wide' => true,
        'pages' => ['checkout.checkout'],
        'icon' => 'fa fa-cash-register',
    ])
        ->and($block->defineFields())->toBe([])
        ->and($block->summary([]))->toBe('');
});

it('builds page view pages that require their block', function(): void {
    $pages = StorefrontBlocks::pageViewPages(['contact' => 'Contact us']);

    expect(array_keys($pages))->toBe(array_keys(StorefrontBlocks::PAGE_VIEWS))
        ->and($pages['contact']['label'])->toBe('Contact us')
        ->and($pages['checkout.checkout']['required'])->toBe(['checkout-form'])
        ->and($pages['checkout.checkout']['default'][0])->toMatchArray([
            'id' => 's_checkout_checkout',
            'layout' => 'cols-1',
            'settings' => ['container' => 'full', 'spacing' => 'none'],
        ])
        ->and($pages['checkout.checkout']['default'][0]['columns'][0]['blocks'][0]['code'])->toBe('checkout-form');
});

it('defines content block fields', function(string $class, string $code, array $fields): void {
    $block = new $class;

    expect($block->getCode())->toBe($code)
        ->and(array_keys($block->defineFields()))->toBe($fields);
})->with([
    [RichText::class, 'rich-text', ['content', 'card']],
    [Image::class, 'image', ['image', 'alt', 'link']],
    [ImageText::class, 'image-text', ['image', 'image_position', 'title', 'content', 'button_text', 'button_link']],
    [CallToAction::class, 'call-to-action', ['title', 'text', 'button_text', 'button_link', 'alignment']],
    [Html::class, 'html', ['html']],
    [Spacer::class, 'spacer', ['size', 'divider']],
    [Hero::class, 'hero', ['title', 'text', 'slider_code', 'slider_interval', 'hide_search', 'redirect_page', 'address_autocomplete', 'show_locate']],
    [Banners::class, 'banners', ['banner_codes']],
    [FeaturedItems::class, 'featured-items', ['title', 'limit', 'items']],
    [FeaturedLocations::class, 'featured-locations', ['title', 'limit']],
]);

it('removes unsafe markup from html settings', function(): void {
    $text = (new RichText)->sanitize(['content' => '<p>Hi</p><script>alert(1)</script>']);
    $html = (new Html)->sanitize(['html' => '<div class="x" onclick="x()">Hi</div><iframe src="https://evil.example"></iframe><iframe src="https://www.youtube.com/embed/a"></iframe>']);

    expect($text['content'])->toBe('<p>Hi</p>')
        ->and($html['html'])->toContain('<div class="x">Hi</div>')
        ->not->toContain('onclick')
        ->not->toContain('evil.example')
        ->toContain('youtube.com/embed/a');
});

it('resolves image, hero, banners and featured data', function(): void {
    $location = currentLocation();
    $menu = Menu::factory()->create(['menu_name' => 'Jollof rice']);
    $menu->locations()->attach($location);

    expect((new Image)->resolve(['image' => 'photo.jpg', 'alt' => 'Room', 'link' => '/']))->toMatchArray(['alt' => 'Room', 'link' => '/'])
        ->and((new Image)->resolve(['image' => 'photo.jpg'])['url'])->toEndWith('photo.jpg')
        ->and((new Image)->resolve([])['url'])->toBeNull()
        ->and((new Image)->summary(['alt' => '', 'image' => 'photo.jpg']))->toBe('photo.jpg')
        ->and((new ImageText)->resolve(['image' => null])['url'])->toBeNull()
        ->and((new Hero)->resolve(['title' => 'Hot food', 'text' => 'Fast', 'slider_code' => 'missing', 'slider_interval' => 1]))->toMatchArray([
            'title' => 'Hot food',
            'text' => 'Fast',
            'slides' => [],
            'slideInterval' => 2000,
        ])
        ->and((new Banners)->resolve(['banner_codes' => ['missing']]))->toBe(['banners' => []])
        ->and((new Banners)->summary(['banner_codes' => ['a', 'b']]))->toBe('a, b')
        ->and((new FeaturedItems)->resolve(['title' => 'Picks', 'items' => []]))->toBe(['title' => 'Picks', 'items' => []])
        ->and((new FeaturedItems)->resolve(['title' => 'Picks', 'items' => [$menu->getKey()], 'limit' => 3])['items'])->toHaveCount(1)
        ->and((new Spacer)->summary(['size' => 'lg']))->toBe('');
});

it('reads values saved for a theme, including removed fields', function(): void {
    ThemeModel::query()->where('code', 'storefront-fixture')->update(['data' => ['hero_title' => 'Saved hero']]);

    expect(ThemeContent::savedValue('storefront-fixture', 'hero_title'))->toBe('Saved hero')
        ->and(ThemeContent::savedValue('storefront-fixture', 'missing', 'default'))->toBe('default')
        ->and(ThemeContent::savedValue('not-a-theme', 'hero_title', 'none'))->toBe('none');
});

it("queries a theme's saved values once", function(): void {
    ThemeModel::query()->where('code', 'storefront-fixture')->update(['data' => ['hero_title' => 'Saved hero', 'hero_text' => 'Fast']]);

    DB::enableQueryLog();

    expect(ThemeContent::savedValue('storefront-fixture', 'hero_title'))->toBe('Saved hero')
        ->and(ThemeContent::savedValue('storefront-fixture', 'hero_text'))->toBe('Fast')
        ->and(ThemeContent::savedValue('storefront-fixture', 'missing', 'default'))->toBe('default')
        ->and(ThemeContent::savedValue('not-a-theme', 'hero_title', 'none'))->toBe('none')
        ->and(collect(DB::getQueryLog())->pluck('query')->filter(fn(string $sql): bool => str_contains($sql, 'themes')))->toHaveCount(2);
});

it('defines the menu page blocks', function(): void {
    $header = new LocalHeader;
    $categories = new CategoryList;
    $items = new MenuItemList;

    expect($categories->isWide())->toBeFalse()
        ->and(array_keys($header->defineFields()))->toBe(['show_cover', 'show_rating', 'show_order_types'])
        ->and(array_keys($categories->defineFields()))->toBe(['show_search', 'show_allergen_filter', 'sticky'])
        ->and(array_keys($items->defineFields()))->toBe(['categories', 'card_style', 'columns', 'item_images', 'category_covers', 'hide_unavailable', 'show_group_order_bar'])
        ->and($items->getDetails())->toMatchArray(['unique' => true, 'wide' => true, 'pages' => ['order']])
        ->and($items->defaults())->toMatchArray(['card_style' => 'row', 'columns' => '3', 'item_images' => 'show', 'hide_unavailable' => false, 'show_group_order_bar' => true])
        ->and($header->summary([]))->toBe('')
        ->and($categories->summary([]))->toBe('')
        ->and($items->summary(['categories' => []]))->toBe('All categories')
        ->and($items->summary(['categories' => ['1', '2']]))->toBe('2 categories')
        ->and(array_keys($items->defineFields()['categories']['options']()))->toBeArray();
});

it('stores switch settings as booleans', function(): void {
    expect((new CategoryList)->sanitize(['show_search' => 0, 'sticky' => '1', 'unknown' => 1]))
        ->toBe(['show_search' => false, 'sticky' => true]);
});

it('leaves out blocks and page views a theme replaces', function(): void {
    $codes = array_map(
        fn(BaseBlock|string $block): string => is_string($block) ? (new $block)->getCode() : $block->getCode(),
        StorefrontBlocks::definitions(except: ['menu-list', 'heading']),
    );

    expect($codes)->not->toContain('menu-list', 'heading')->toContain('checkout-form', 'rich-text')
        ->and(StorefrontBlocks::menuBlocks())->toHaveCount(4)
        ->and(StorefrontBlocks::pageViewPages(except: ['order']))->not->toHaveKey('order')->toHaveKey('contact');
});

it('defines the checkout page blocks', function(): void {
    $form = new CheckoutForm;
    $summary = new OrderSummary;

    expect(array_keys($form->defineFields()))->toBe(['telephone_required', 'show_comment', 'show_delivery_comment', 'terms_page', 'show_step_numbers'])
        ->and(array_keys($summary->defineFields()))->toBe(['show_items', 'show_item_options', 'sticky'])
        ->and($form->getDetails())->toMatchArray(['unique' => true, 'wide' => true, 'pages' => ['checkout.checkout']])
        ->and($form->defaults())->toMatchArray(['telephone_required' => true, 'terms_page' => 'terms-and-conditions'])
        ->and($form->defineFields()['terms_page']['options']())->toBeArray()
        ->and($form->summary([]))->toBe('')
        ->and($summary->summary([]))->toBe('')
        ->and(StorefrontBlocks::checkoutBlocks())->toHaveCount(3);
});

it('reads block settings for a page', function(): void {
    resolve(BlockManager::class)->registerBlocks('storefront-fixture', StorefrontBlocks::checkoutBlocks());

    expect(PageSections::blockSettings('locations', 'checkout-form'))->toBe([])
        ->and(PageSections::blockSettings('checkout.checkout', 'checkout-form'))->toBe([]);

    ThemePage::create([
        'theme_code' => 'storefront-fixture',
        'page_code' => 'contact',
        'sections' => [['id' => 's_1', 'columns' => [['blocks' => [['code' => 'hero', 'settings' => []]]]]]],
    ]);

    expect(PageSections::blockSettings('contact', 'checkout-form'))->toBe([]);
});

it('defines the page blocks with their names, fields and pages', function(): void {
    $blocks = array_map(fn(string $class): PageBlock => new $class, StorefrontBlocks::pageBlocks());
    $codes = array_map(fn(PageBlock $block): string => $block->getCode(), $blocks);

    expect($codes)->toHaveCount(count(array_unique($codes)))
        ->toContain(...array_diff(array_column(StorefrontBlocks::PAGE_VIEWS, 0), ['menu-list', 'checkout-form', 'account-order', 'account-reservation']));

    foreach ($blocks as $block) {
        $details = $block->getDetails();
        expect(Lang::has($details['name']))->toBeTrue($details['name'])
            ->and(Lang::has($details['description']))->toBeTrue($details['description'])
            ->and($block->summary([]))->toBe('');

        foreach ($block->defineFields() as $name => $field) {
            expect(Lang::has($field['label']))->toBeTrue($details['code'].'.'.$name);
        }

        if (!in_array($details['code'], ['page-header', 'breadcrumb', 'ordering-from'], true)) {
            expect($details['pages'])->not->toBeEmpty();
        }
    }

    expect((new AccountNav)->getDetails()['pages'])->toBe(PageBlock::ACCOUNT_PAGES)
        ->and((new LoginForm)->getDetails()['pages'])->toBe(['account.login'])
        ->and((new PageHeader)->getDetails())->toMatchArray(['pages' => [], 'wide' => false])
        ->and((new ContactForm)->defaults())->toMatchArray(['telephone_required' => true, 'show_subject' => true])
        ->and((new PageHeader)->defaults())->toMatchArray(['alignment' => 'center', 'location_photo' => false, 'overlap' => true])
        ->and((new LocationList)->defineFields()['columns']['validationRule'])->toBe(['in:2,3,4'])
        ->and((new PageHeader)->defineFields()['title']['validationRule'])->toBe(['nullable', 'string', 'max:120']);
});

it('names every buildable page', function(): void {
    foreach (array_keys(StorefrontBlocks::PAGE_VIEWS) as $pageCode) {
        expect(Lang::has(StorefrontBlocks::pageLabel($pageCode)))->toBeTrue($pageCode);
    }

    expect(StorefrontBlocks::pageLabel('account.login'))->toBe('igniterlabs.storefront::default.builder.page_account_login');
});

it('defines the breadcrumb, checkout header, order options and ordering from blocks', function(): void {
    expect((new Breadcrumb)->getDetails()['pages'])->toBe([])
        ->and((new Breadcrumb)->defaults())->toMatchArray(['show_menu_link' => true])
        ->and((new CheckoutHeader)->getDetails()['pages'])->toBe(['checkout.checkout'])
        ->and(array_keys((new CheckoutHeader)->defineFields()))->toBe(['title', 'show_location'])
        ->and((new OrderOptions)->defaults())->toBe(['preview_mode' => false, 'show_table_actions' => true, 'show_group_order' => true])
        ->and((new OrderOptions)->getDetails()['pages'])->toBe(['order'])
        ->and(array_keys((new OrderingFrom)->defineFields()))->toBe(['show_hours', 'show_order_types', 'overlap']);
});

it('resolves the hero search settings', function(): void {
    $hero = new Hero;

    expect($hero->defaults())->toMatchArray(['hide_search' => false, 'redirect_page' => 'order', 'address_autocomplete' => true, 'show_locate' => true])
        ->and($hero->resolve(['hide_search' => false])['orderButton'])->toBeNull()
        ->and($hero->resolve(['hide_search' => true])['orderButton'])->toBeString()
        ->and($hero->resolve(['redirect_page' => 'booking', 'address_autocomplete' => false, 'show_locate' => false]))->toMatchArray([
            'redirectPage' => 'booking',
            'addressAutocomplete' => false,
            'showLocate' => false,
        ])
        ->and($hero->resolve(['redirect_page' => 'admin'])['redirectPage'])->toBe('order')
        ->and(array_keys($hero->defineFields()['redirect_page']['options']()))->toBe(Hero::REDIRECT_PAGES);
});

it('resolves featured locations, shuffled up to the limit', function(): void {
    resolve(\Igniter\Local\Classes\FeaturedLocations::class)->clearCache();
    $featured = Location::factory()->count(3)->create(['location_status' => true]);
    $disabled = Location::factory()->create(['location_status' => false]);
    Event::listen('igniter.local.featuredLocationIds', fn(): array => [...$featured->modelKeys(), $disabled->getKey()]);

    $resolved = (new FeaturedLocations)->resolve(['title' => 'Stars', 'limit' => 2]);

    expect($resolved['title'])->toBe('Stars')
        ->and($resolved['locations'])->toHaveCount(2)
        ->and(array_diff(array_column($resolved['locations'], 'id'), $featured->modelKeys()))->toBe([])
        ->and($resolved['locations'][0])->toHaveKeys(['name', 'slug', 'address', 'image', 'featured']);

    resolve(\Igniter\Local\Classes\FeaturedLocations::class)->clearCache();
});

it('resolves no featured locations without any', function(): void {
    resolve(\Igniter\Local\Classes\FeaturedLocations::class)->clearCache();

    expect((new FeaturedLocations)->resolve(['title' => 'Stars'])['locations'])->toBe([]);
});
