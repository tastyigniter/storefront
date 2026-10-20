<?php

declare(strict_types=1);

namespace Igniter\Storefront\Tests\Http\Controllers\Account;

use Igniter\PayRegister\Models\PaymentProfile;
use Igniter\User\Facades\Auth;
use Igniter\User\Models\Address;
use Igniter\User\Models\Customer;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia;

it('shows the account dashboard', function(): void {
    actingAsCustomer();

    $this->get(route('igniter.theme.account.account'))
        ->assertOk()
        ->assertInertia(fn(AssertableInertia $page): AssertableInertia => $page->component('Account/Dashboard'));
});

it('updates the customer details', function(): void {
    $customer = actingAsCustomer(Customer::factory()->create(['telephone' => '0100']));

    $this->from(route('igniter.theme.account.account'))
        ->put(route('igniter.theme.account.profile'), [
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
        ])
        ->assertRedirect(route('igniter.theme.account.account'))
        ->assertSessionHas('success', 'Your details have been saved.');

    $customer->refresh();
    expect($customer->first_name)->toBe('Ada')
        ->and($customer->last_name)->toBe('Lovelace')
        ->and($customer->telephone)->toBe('0100');
});

it('changes the password when one is given', function(): void {
    $customer = actingAsCustomer();

    $this->put(route('igniter.theme.account.profile'), [
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
        'telephone' => '0200',
        'password' => 'new-secret',
        'password_confirmation' => 'new-secret',
    ])->assertSessionHasNoErrors();

    $customer->refresh();
    expect($customer->telephone)->toBe('0200')
        ->and(Hash::check('new-secret', $customer->password))->toBeTrue();
});

it('deletes the account, its addresses and saved cards', function(): void {
    $customer = actingAsCustomer();
    $address = Address::factory()->create(['customer_id' => $customer->getKey()]);
    $profile = PaymentProfile::factory()->create(['customer_id' => $customer->getKey()]);

    $this->delete(route('igniter.theme.account.delete'), ['password' => 'password'])
        ->assertRedirect(route('igniter.theme.home'))
        ->assertSessionHas('success', 'Your account has been deleted.');

    expect(Customer::find($customer->getKey()))->toBeNull()
        ->and(Address::find($address->getKey()))->toBeNull()
        ->and(PaymentProfile::find($profile->getKey()))->toBeNull()
        ->and(Auth::check())->toBeFalse();
});

it('keeps the account when the password is wrong', function(): void {
    $customer = actingAsCustomer();

    $this->delete(route('igniter.theme.account.delete'), ['password' => 'wrong'])
        ->assertSessionHasErrors('password');

    expect(Customer::find($customer->getKey()))->not->toBeNull();
});

it('requires a password to delete the account', function(): void {
    actingAsCustomer();

    $this->delete(route('igniter.theme.account.delete'), [])
        ->assertSessionHasErrors('password');
});
