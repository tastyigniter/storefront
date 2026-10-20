<?php

declare(strict_types=1);

namespace Igniter\Storefront\Tests\Http\Controllers\Account;

use Igniter\System\Models\Country;
use Igniter\User\Models\Address;
use Igniter\User\Models\Customer;
use Inertia\Testing\AssertableInertia;

function addressData(array $overrides = []): array
{
    return array_merge([
        'address_1' => '1 Test Street',
        'address_2' => 'Flat 2',
        'city' => 'London',
        'state' => 'Greater London',
        'postcode' => 'E1 6AN',
        'country_id' => Country::query()->value('country_id'),
    ], $overrides);
}

it('lists the saved addresses and countries', function(): void {
    $customer = actingAsCustomer();
    $address = Address::factory()->create(['customer_id' => $customer->getKey()]);

    $this->get(route('igniter.theme.account.address'))
        ->assertOk()
        ->assertInertia(fn(AssertableInertia $page): AssertableInertia => $page
            ->component('Account/Address')
            ->has('countries')
            ->has('addresses', 1)
            ->where('addresses.0.id', $address->getKey())
            ->where('pagination.lastPage', 1)
            ->where('editing', null));
});

it('opens an address for editing', function(): void {
    $customer = actingAsCustomer();
    $address = Address::factory()->create(['customer_id' => $customer->getKey(), 'state' => 'Kent']);

    $this->get(route('igniter.theme.account.address', ['address' => $address->getKey()]))
        ->assertInertia(fn(AssertableInertia $page): AssertableInertia => $page
            ->where('editing.id', $address->getKey())
            ->where('editing.state', 'Kent'));
});

it('rejects an address that fails validation', function(): void {
    actingAsCustomer();

    $this->from(route('igniter.theme.account.address'))
        ->post(route('igniter.theme.account.address.store'), addressData(['address_1' => '']))
        ->assertSessionHasErrors(['address_1']);
});

it('saves a new address', function(): void {
    $customer = actingAsCustomer();

    $this->post(route('igniter.theme.account.address.store'), addressData())
        ->assertRedirect(route('igniter.theme.account.address'))
        ->assertSessionHas('success', 'Address saved.');

    expect($customer->addresses()->first())
        ->address_1->toBe('1 Test Street')
        ->state->toBe('Greater London');
});

it('updates an existing address', function(): void {
    $customer = actingAsCustomer();
    $address = Address::factory()->create(['customer_id' => $customer->getKey()]);

    $this->post(route('igniter.theme.account.address.store'), addressData([
        'address_id' => $address->getKey(),
        'city' => 'Leeds',
    ]))->assertSessionHasNoErrors();

    expect($address->fresh()->city)->toBe('Leeds');
});

it("will not edit another customer's address", function(): void {
    actingAsCustomer();
    $address = Address::factory()->create(['customer_id' => Customer::factory()->create()->getKey()]);

    $this->from(route('igniter.theme.account.address'))
        ->post(route('igniter.theme.account.address.store'), addressData(['address_id' => $address->getKey()]))
        ->assertSessionHasErrors(['form' => 'That address could not be found.']);
});

it('removes an address', function(): void {
    $customer = actingAsCustomer();
    $address = Address::factory()->create(['customer_id' => $customer->getKey()]);

    $this->from(route('igniter.theme.account.address'))
        ->delete(route('igniter.theme.account.address.destroy', ['addressId' => $address->getKey()]))
        ->assertSessionHas('success', 'Address removed.');

    expect(Address::find($address->getKey()))->toBeNull();
});

it('reports an address it cannot find', function(): void {
    actingAsCustomer();

    $this->from(route('igniter.theme.account.address'))
        ->delete(route('igniter.theme.account.address.destroy', ['addressId' => 999999]))
        ->assertSessionHasErrors(['form' => 'That address could not be found.']);
});
