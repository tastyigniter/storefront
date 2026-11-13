<?php

declare(strict_types=1);

namespace Igniter\Storefront\Tests\Support;

use Igniter\Main\Classes\BlockManager;
use Igniter\Main\Models\ThemePageLayout;
use Igniter\Pages\Models\Page;
use Igniter\Storefront\Support\PageSections;
use Igniter\Storefront\Tests\Fixture\HeroBlock;
use Inertia\Testing\AssertableInertia;

beforeEach(function(): void {
    resolve(BlockManager::class)->registerBlocks('storefront-fixture', [HeroBlock::class]);
});

it('returns no sections without a route or for pages that are not buildable', function(): void {
    expect(PageSections::forRoute(null))->toBe([])
        ->and(PageSections::forRoute('igniter.theme.locations'))->toBe([]);
});

it('resolves the theme default sections for a buildable page', function(): void {
    $sections = PageSections::forRoute('igniter.theme.home');

    expect($sections)->toHaveCount(1)
        ->and($sections[0]['id'])->toBe('s_home_hero')
        ->and($sections[0]['layout'])->toBe('sidebar-right-4')
        ->and($sections[0]['equal'])->toBeFalse()
        ->and(array_column($sections[0]['columns'], 'span'))->toBe([8, 4])
        ->and($sections[0]['columns'][0]['blocks'][0])->toMatchArray([
            'id' => 'b_home_hero',
            'code' => 'hero',
            'settings' => ['title' => 'Hot food, fast'],
            'data' => ['heading' => 'HOT FOOD, FAST'],
        ]);
});

it('resolves saved sections over the theme default', function(): void {
    ThemePageLayout::create([
        'theme_code' => 'storefront-fixture',
        'page_code' => 'contact',
        'sections' => [['id' => 's_saved', 'layout' => 'cols-2', 'columns' => [['blocks' => [['code' => 'hero']]]]]],
    ]);

    $sections = PageSections::forRoute('contact');

    expect($sections[0]['id'])->toBe('s_saved')
        ->and($sections[0]['equal'])->toBeTrue()
        ->and($sections[0]['columns'][0]['blocks'][0]['data'])->toBe(['heading' => 'WELCOME']);
});

it('resolves sections for static pages built with the page builder', function(): void {
    $page = new Page([
        'editor' => Page::EDITOR_BUILDER,
        'sections' => [['id' => 's_static', 'columns' => [['blocks' => [['code' => 'hero', 'settings' => ['title' => 'About']]]]]]],
    ]);

    expect(PageSections::forStaticPage($page)[0]['columns'][0]['blocks'][0]['data'])->toBe(['heading' => 'ABOUT'])
        ->and(PageSections::forStaticPage(new Page(['editor' => Page::EDITOR_CONTENT])))->toBe([]);
});

it('shares page builder sections with storefront pages', function(): void {
    currentLocation();

    $this->get(route('igniter.theme.home'))
        ->assertOk()
        ->assertInertia(fn(AssertableInertia $page): AssertableInertia => $page
            ->component('Home/Index')
            ->where('sections.0.id', 's_home_hero')
            ->where('sections.0.columns.0.blocks.0.data.heading', 'HOT FOOD, FAST'));

    $this->get(route('igniter.theme.locations'))
        ->assertOk()
        ->assertInertia(fn(AssertableInertia $page): AssertableInertia => $page->where('sections', []));
});

it('passes static page builder sections to the page', function(): void {
    $page = Page::query()->where('status', true)->firstOrFail();
    $page->editor = Page::EDITOR_BUILDER;
    $page->sections = [['id' => 's_about', 'columns' => [['blocks' => [['code' => 'hero', 'settings' => ['title' => 'Our story']]]]]]];
    $page->save();

    $this->get('/'.$page->permalink_slug)
        ->assertOk()
        ->assertInertia(fn(AssertableInertia $inertia): AssertableInertia => $inertia
            ->component('Pages/Show')
            ->where('sections.0.id', 's_about')
            ->where('sections.0.columns.0.blocks.0.data.heading', 'OUR STORY'));
});
