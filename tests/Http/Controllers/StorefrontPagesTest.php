<?php

declare(strict_types=1);

namespace Igniter\Storefront\Tests\Http\Controllers;

use Igniter\Cart\Models\Category;
use Igniter\Cart\Models\Menu;
use Igniter\Frontend\Models\CaptchaSettings;
use Igniter\Local\Facades\Location;
use Igniter\Local\Models\Location as LocationModel;
use Igniter\Pages\Models\Page;
use Igniter\Storefront\Actions\ManageAccount;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia;
use RuntimeException;

it('shows the homepage', function(): void {
    currentLocation();
    setThemeSettings(['hero_title' => 'Hot food, fast']);

    $this->get(route('igniter.theme.home'))
        ->assertOk()
        ->assertInertia(fn(AssertableInertia $page): AssertableInertia => $page
            ->component('Home/Index')
            ->where('home.heroTitle', 'Hot food, fast')
            ->has('storefront.cart')
            ->has('site.footerMenu'));
});

it('shows the menu grouped by category', function(): void {
    $location = currentLocation();
    $category = Category::factory()->create(['name' => 'Mains', 'priority' => 1]);
    $menu = Menu::factory()->create(['menu_name' => 'Jollof rice']);
    $menu->categories()->attach($category);
    $menu->locations()->attach($location);

    $this->get(route('igniter.theme.order', ['search' => 'jollof']))
        ->assertOk()
        ->assertInertia(fn(AssertableInertia $page): AssertableInertia => $page
            ->component('Order/Menu')
            ->where('search', 'jollof')
            ->where('groups.0.name', 'Mains')
            ->where('groups.0.items.0.name', 'Jollof rice')
            ->has('fulfilmentOptions'));
});

it('opens the menu for the location named in the address', function(): void {
    currentLocation();
    $other = LocationModel::factory()->create(['location_name' => 'Other Kitchen']);
    // A real request starts with no in-memory location, so the address slug is what selects the kitchen.
    (fn(): null => $this->model = null)->call(resolve('location'));

    $this->get(route('igniter.theme.order', ['location' => $other->permalink_slug]))->assertOk();

    expect(Location::getId())->toBe($other->getKey());
});

it('sends an unknown location back home', function(): void {
    currentLocation();

    $this->get('/not-a-kitchen/order')->assertRedirect(route('igniter.theme.home'));
});

it('sends /order to the current location menu', function(): void {
    $location = currentLocation();

    $this->get(route('igniter.theme.order.current'))
        ->assertRedirect(route('igniter.theme.order', ['location' => $location->permalink_slug]));
});

it('shows an empty menu when there is no location', function(): void {
    LocationModel::query()->delete();
    Location::resetSession();
    (fn(): null => $this->model = null)->call(resolve('location'));

    $this->get(route('igniter.theme.order.current'))
        ->assertRedirect(route('igniter.theme.locations'));
});

it('shows the contact page', function(): void {
    $this->get(route('igniter.theme.contact'))
        ->assertOk()
        ->assertInertia(fn(AssertableInertia $page): AssertableInertia => $page
            ->component('Contact/Index')
            ->has('subjects', 3));
});

it('sends a contact message', function(): void {
    Mail::fake();

    $this->from(route('igniter.theme.contact'))
        ->post(route('igniter.theme.contact.store'), [
            'full_name' => 'Jane Doe',
            'email' => 'jane@example.test',
            'telephone' => '07700900000',
            'subject' => 'contact.subject_comment',
            'comment' => 'Loved it',
        ])
        ->assertRedirect(route('igniter.theme.contact'))
        ->assertSessionHas('success', 'alert.contact_sent');
});

it('reports a contact message that cannot be sent', function(): void {
    $this->mock(ManageAccount::class)->shouldReceive('contact')->andThrow(new RuntimeException('mail down'));

    $this->from(route('igniter.theme.contact'))
        ->post(route('igniter.theme.contact.store'), [
            'full_name' => 'Jane Doe',
            'email' => 'jane@example.test',
            'telephone' => '07700900000',
            'subject' => 'contact.subject_comment',
        ])
        ->assertSessionHasErrors(['form' => 'alert.something_wrong']);
});

it('checks reCAPTCHA on the contact form when it is set up', function(): void {
    CaptchaSettings::set(['api_site_key' => 'site-key', 'api_secret_key' => 'secret-key']);

    $this->post(route('igniter.theme.contact.store'), [
        'full_name' => 'Jane Doe',
        'email' => 'jane@example.test',
        'telephone' => '07700900000',
        'subject' => 'contact.subject_comment',
    ])->assertSessionHasErrors('g-recaptcha-response');
});

it('renders a static page through the theme', function(): void {
    $page = Page::query()->where('status', true)->firstOrFail();

    $this->get('/'.$page->permalink_slug)
        ->assertOk()
        ->assertInertia(fn(AssertableInertia $inertia): AssertableInertia => $inertia
            ->component('Pages/Show')
            ->where('page.title', (string)$page->title)
            ->has('pagesMenu'));
});

it('returns 404 for a disabled static page', function(): void {
    $page = Page::query()->firstOrFail();
    $page->status = false;
    $page->save();

    $this->get('/'.$page->permalink_slug)->assertNotFound();
});
