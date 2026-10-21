<?php

declare(strict_types=1);

namespace Igniter\Storefront\Tests\Http;

use Igniter\Flame\Exception\ApplicationException;
use Igniter\Local\Models\Location as LocationModel;
use Igniter\Reservation\Models\DiningArea;
use Igniter\Reservation\Models\DiningTable;
use Igniter\Storefront\Support\DineInTables;
use Igniter\Storefront\Support\GiftCards;
use Igniter\Storefront\Support\LocationDirectory;
use Igniter\Storefront\Support\LoyaltyRedeem;
use Igniter\Storefront\Support\SocialiteLogin;
use Igniter\Storefront\Support\VendorSignup;
use Igniter\System\Models\Country;
use Igniter\User\Models\Customer;
use Inertia\Testing\AssertableInertia;
use Symfony\Component\HttpFoundation\RedirectResponse;

afterEach(function(): void {
    DineInTables::$enabled = false;
    SocialiteLogin::$manager = null;
    LoyaltyRedeem::$state = null;
    LoyaltyRedeem::$history = null;
    GiftCards::$options = null;
    VendorSignup::$enabled = false;
    VendorSignup::$handler = null;
});

it('rejects dine-in from the header when the location does not offer it', function(): void {
    $location = currentLocation();

    $this->from(route('igniter.theme.order'))
        ->post(route('igniter.theme.session'), ['location_id' => $location->getKey(), 'order_type' => 'dinein'])
        ->assertSessionHasErrors(['form' => 'alert.no_dinein']);
});

it('lists dining areas and remembers the chosen table', function(): void {
    expect(fn(): bool => DineInTables::remember(1, 1, 1))
        ->toThrow(ApplicationException::class, 'alert.no_dinein');

    $location = currentLocation();
    $area = DiningArea::factory()->create(['location_id' => $location->getKey(), 'name' => 'Patio']);
    $other = DiningArea::factory()->create(['location_id' => $location->getKey(), 'name' => 'Indoors']);
    DiningTable::factory()->create([
        'dining_area_id' => $area->getKey(),
        'name' => 'Window',
        'min_capacity' => 2,
        'max_capacity' => 4,
        'is_enabled' => true,
    ]);
    $table = DiningTable::factory()->create([
        'dining_area_id' => $other->getKey(),
        'name' => 'Booth',
        'min_capacity' => 0,
        'max_capacity' => 0,
        'is_enabled' => true,
    ]);
    DiningTable::factory()->create([
        'dining_area_id' => $other->getKey(),
        'name' => 'Bar',
        'min_capacity' => 0,
        'max_capacity' => 6,
        'is_enabled' => true,
    ]);

    DineInTables::$enabled = true;

    $listed = DineInTables::present($location);

    expect($listed['areas'])->toHaveCount(2)
        ->and(collect($listed['tables'])->pluck('guests')->sort()->values()->all())->toBe(['', '2–4', '6'])
        ->and(DineInTables::present(null))->toBeNull();

    DineInTables::remember((int)$location->getKey(), (int)$other->getKey(), (int)$table->getKey());

    expect(session('igniterlabs_dinein.table_id'))->toBe((int)$table->getKey())
        ->and(DineInTables::present($location)['tableId'])->toBe((int)$table->getKey());

    expect(fn(): bool => DineInTables::remember((int)$location->getKey(), (int)$area->getKey(), 999))
        ->toThrow(ApplicationException::class, 'alert.table_required');

    $solo = LocationModel::factory()->create();
    $only = DiningArea::factory()->create(['location_id' => $solo->getKey(), 'name' => 'Only']);

    expect(DineInTables::present($solo)['areaId'])->toBe((int)$only->getKey());

    $empty = LocationModel::factory()->create();

    expect(DineInTables::present($empty))->toMatchArray(['areas' => [], 'tables' => [], 'areaId' => null, 'tableId' => null]);
});

it('names the table on a dine-in order', function(): void {
    currentLocation();
    $order = paidOrder();

    expect(DineInTables::details($order))->toBeNull();

    $order->order_type = 'dinein';
    $order->setAttribute('dining_table_id', 12);

    expect(DineInTables::details($order))->toBe(['name' => '#12', 'area' => null, 'guests' => '']);

    $order->setAttribute('dining_table_id', null);

    expect(DineInTables::details($order))->toBeNull();
});

it('sends social sign-in to the confirm-email step', function(): void {
    $this->get(route('igniter.theme.account.confirm'))
        ->assertRedirect(route('igniter.theme.account.login'));

    $this->from(route('igniter.theme.account.login'))
        ->post(route('igniter.theme.account.confirm.store'), ['email' => 'new@example.test'])
        ->assertSessionHasErrors(['form' => 'alert.socialite_missing']);

    $this->get(route('igniter.theme.account.login'))
        ->assertInertia(fn(AssertableInertia $page): AssertableInertia => $page->where('providers', []));

    $manager = new class
    {
        public ?object $data = null;

        public ?object $saved = null;

        public function listProviders(): array
        {
            return [
                'google' => ['code' => 'google', 'label' => 'Google'],
            ];
        }

        public function listProviderLinks(): array
        {
            return ['google' => 'https://social.test/auth?driver=google'];
        }

        public function getProviderData(): ?object
        {
            return $this->data;
        }

        public function setProviderData(object $data): void
        {
            $this->saved = $data;
        }

        public function completeCallback(): RedirectResponse
        {
            return new RedirectResponse('/account');
        }
    };
    $manager->data = (object)['user' => (object)['email' => '']];
    SocialiteLogin::$manager = $manager;

    expect(SocialiteLogin::links()[0])->toMatchArray([
        'code' => 'google',
        'label' => 'Google',
        'url' => 'https://social.test/auth?driver=google&'.http_build_query([
            'success' => route('igniter.theme.account.account'),
            'error' => route('igniter.theme.account.login'),
        ]),
    ])
        ->and(SocialiteLogin::pendingEmail())->toBe('');

    $this->get(route('igniter.theme.account.confirm'))
        ->assertOk()
        ->assertInertia(fn(AssertableInertia $page): AssertableInertia => $page
            ->component('Account/ConfirmEmail')
            ->where('email', ''));

    $this->post(route('igniter.theme.account.confirm.store'), ['email' => 'new@example.test'])
        ->assertRedirect('/account');

    expect($manager->saved->user->email)->toBe('new@example.test');

    Customer::factory()->create(['email' => 'taken@example.test']);

    $this->from(route('igniter.theme.account.confirm'))
        ->post(route('igniter.theme.account.confirm.store'), ['email' => 'taken@example.test'])
        ->assertSessionHasErrors('email');
});

it('hides loyalty, gift cards and vendor registration until those extensions are installed', function(): void {
    actingAsCustomer();

    $this->get(route('igniter.theme.account.points'))->assertNotFound();
    $this->post(route('igniter.theme.cart.loyalty'), ['amount' => 5])->assertNotFound();
    $this->delete(route('igniter.theme.cart.loyalty'))->assertNotFound();
    $this->get(route('igniter.theme.gift-cards'))->assertNotFound();
    $this->get(route('igniter.theme.vendor.register'))->assertNotFound();
    $this->post(route('igniter.theme.vendor.register'), [])->assertForbidden();
});

it('redeems points and shows the history when loyalty is available', function(): void {
    actingAsCustomer();
    LoyaltyRedeem::$state = [
        'available' => 20,
        'minimum' => 5,
        'applied' => 0,
        'canApply' => true,
        'cartEmpty' => false,
        'signedIn' => true,
    ];
    currentLocation();

    $this->from(route('igniter.theme.order'))
        ->post(route('igniter.theme.cart.loyalty'), ['amount' => 'lots'])
        ->assertSessionHasErrors('amount');

    $this->from(route('igniter.theme.order'))
        ->post(route('igniter.theme.cart.loyalty'), ['amount' => 10])
        ->assertRedirect(route('igniter.theme.order'))
        ->assertSessionHasNoErrors();

    $this->from(route('igniter.theme.order'))
        ->post(route('igniter.theme.cart.loyalty'), ['amount' => 2])
        ->assertSessionHasErrors(['form' => 'alert.loyalty_minimum']);

    LoyaltyRedeem::$state['signedIn'] = false;

    $this->from(route('igniter.theme.order'))
        ->post(route('igniter.theme.cart.loyalty'), ['amount' => 10])
        ->assertSessionHasErrors(['form' => 'alert.loyalty_login']);

    LoyaltyRedeem::$state['signedIn'] = true;
    LoyaltyRedeem::$state['cartEmpty'] = true;

    $this->from(route('igniter.theme.order'))
        ->post(route('igniter.theme.cart.loyalty'), ['amount' => 10])
        ->assertSessionHasErrors(['form' => 'alert.loyalty_empty']);

    LoyaltyRedeem::$state['cartEmpty'] = false;
    LoyaltyRedeem::$state['available'] = 4;

    $this->from(route('igniter.theme.order'))
        ->post(route('igniter.theme.cart.loyalty'), ['amount' => 10])
        ->assertSessionHasErrors(['form' => 'alert.loyalty_points']);

    LoyaltyRedeem::$state['available'] = 20;
    LoyaltyRedeem::$state['applied'] = 10;

    $this->from(route('igniter.theme.order'))
        ->post(route('igniter.theme.cart.loyalty'), ['amount' => 0])
        ->assertRedirect(route('igniter.theme.order'));

    $this->from(route('igniter.theme.order'))
        ->delete(route('igniter.theme.cart.loyalty.remove'))
        ->assertRedirect(route('igniter.theme.order'));

    LoyaltyRedeem::$history = [
        'earned' => 30,
        'redeemed' => 10,
        'pending' => 0,
        'available' => 20,
        'rows' => [['date' => '19 Oct 2026', 'type' => 'Order', 'earned' => '30', 'used' => '10', 'status' => 'Available']],
    ];

    $this->get(route('igniter.theme.account.points'))
        ->assertOk()
        ->assertInertia(fn(AssertableInertia $page): AssertableInertia => $page
            ->component('Account/Points')
            ->where('available', 20)
            ->where('rows.0.type', 'Order'));
});

it('rejects a loyalty amount of zero through the guard', function(): void {
    expect(fn() => LoyaltyRedeem::guard(0, LoyaltyRedeem::normalise([
        'available' => 10,
        'minimum' => 1,
        'applied' => 0,
        'canApply' => true,
        'cartEmpty' => false,
        'signedIn' => true,
    ])))->toThrow(ApplicationException::class, 'alert.loyalty_amount');
});

it('shows the gift card purchase page when gift cards are connected', function(): void {
    GiftCards::$options = ['companyId' => 'co_123', 'language' => 'en-GB'];

    $this->get(route('igniter.theme.gift-cards'))
        ->assertOk()
        ->assertInertia(fn(AssertableInertia $page): AssertableInertia => $page
            ->component('GiftCards/Index')
            ->where('options.companyId', 'co_123'));
});

it('lists locations and filters them by cuisine id', function(): void {
    currentLocation(['location_name' => 'Soho']);

    $this->get(route('igniter.theme.locations'))
        ->assertOk()
        ->assertInertia(fn(AssertableInertia $page): AssertableInertia => $page
            ->component('Locations/Index')
            ->where('cuisines', [])
            ->where('locations', fn($locations): bool => collect($locations)->contains('name', 'Soho')));

    $this->get(route('igniter.theme.locations', ['cuisine' => 4]))
        ->assertInertia(fn(AssertableInertia $page): AssertableInertia => $page
            ->where('locations', fn($locations): bool => collect($locations)->contains('name', 'Soho')));

    $locations = LocationDirectory::locations(null);

    expect(LocationDirectory::filter($locations, []))->toBe([])
        ->and(LocationDirectory::filter($locations, [$locations[0]['id']]))->toHaveCount(1)
        ->and(LocationDirectory::filter($locations, [999]))->toBe([]);
});

it('registers a vendor when registration is open', function(): void {
    VendorSignup::$enabled = true;
    VendorSignup::$handler = fn(array $data): string => 'Registered '.$data['name'];

    $this->get(route('igniter.theme.vendor.register'))
        ->assertOk()
        ->assertInertia(fn(AssertableInertia $page): AssertableInertia => $page
            ->component('Vendor/Register')
            ->where('cuisines', [])
            ->where('countryId', (int)Country::getDefaultKey()));

    $this->post(route('igniter.theme.vendor.register'), [
        'name' => 'Harbour Kitchen',
        'description' => 'Fish',
        'email' => 'kitchen@example.test',
        'telephone' => '02070000000',
        'street' => '1 Harbour Road',
        'city' => 'London',
        'postcode' => 'E1 1AA',
        'countryId' => (int)Country::getDefaultKey(),
        'admin' => [
            'name' => 'Ada Owner',
            'email' => 'ada@example.test',
            'username' => 'ada-owner',
            'password' => 'Harbour1!',
            'password_confirm' => 'Harbour1!',
        ],
    ])->assertRedirect(route('igniter.theme.home'))
        ->assertSessionHas('success', 'Registered Harbour Kitchen');
});
