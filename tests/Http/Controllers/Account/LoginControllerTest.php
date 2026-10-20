<?php

declare(strict_types=1);

namespace Igniter\Storefront\Tests\Http\Controllers\Account;

use Igniter\System\Models\Settings;
use Igniter\User\Facades\Auth;
use Igniter\User\Models\Customer;
use Inertia\Testing\AssertableInertia;

it('shows the login page', function(): void {
    $this->get(route('igniter.theme.account.login'))
        ->assertOk()
        ->assertInertia(fn(AssertableInertia $page): AssertableInertia => $page
            ->component('Account/Login')
            ->where('registrationAllowed', true));
});

it('says when registration is turned off', function(): void {
    Settings::set('allow_registration', 0);

    $this->get(route('igniter.theme.account.login'))
        ->assertInertia(fn(AssertableInertia $page): AssertableInertia => $page->where('registrationAllowed', false));
});

it('signs a customer in and sends them to their account', function(): void {
    $customer = Customer::factory()->create(['email' => 'jane@example.test', 'is_activated' => true]);

    $this->post(route('igniter.theme.account.login'), [
        'email' => 'jane@example.test',
        'password' => 'password',
    ])->assertRedirect(route('igniter.theme.account.account'));

    expect(Auth::customer()?->getKey())->toBe($customer->getKey());
});

it('shows an error when the password is wrong', function(): void {
    Customer::factory()->create(['email' => 'jane@example.test', 'is_activated' => true]);

    $this->from(route('igniter.theme.account.login'))
        ->post(route('igniter.theme.account.login'), [
            'email' => 'jane@example.test',
            'password' => 'not-the-password',
        ])
        ->assertRedirect(route('igniter.theme.account.login'))
        ->assertSessionHasErrors();

    expect(Auth::check())->toBeFalse();
});

it('validates the login form', function(): void {
    $this->post(route('igniter.theme.account.login'), [])
        ->assertSessionHasErrors(['email', 'password']);
});

it('signs the customer out', function(): void {
    actingAsCustomer();

    $this->post(route('igniter.theme.account.logout'))
        ->assertRedirect(route('igniter.theme.home'));

    expect(Auth::check())->toBeFalse();
});
