<?php

declare(strict_types=1);

namespace Igniter\Storefront\Tests\Http\Controllers\Account;

use Igniter\System\Models\Settings;
use Igniter\User\Facades\Auth;
use Igniter\User\Models\Customer;
use Igniter\User\Models\CustomerGroup;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia;

function registration(array $overrides = []): array
{
    return array_merge([
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'email' => 'jane@example.test',
        'password' => 'secret-123',
        'password_confirmation' => 'secret-123',
        'telephone' => '07700900000',
        'newsletter' => true,
    ], $overrides);
}

beforeEach(function(): void {
    Mail::fake();
});

it('shows the register page', function(): void {
    $this->get(route('igniter.theme.account.register'))
        ->assertOk()
        ->assertInertia(fn(AssertableInertia $page): AssertableInertia => $page
            ->component('Account/Register')
            ->where('registrationAllowed', true));
});

it('registers a customer who is active straight away', function(): void {
    CustomerGroup::getDefault()?->update(['approval' => 0]);

    $this->post(route('igniter.theme.account.register'), registration())
        ->assertRedirect(route('igniter.theme.account.account'))
        ->assertSessionHas('success', 'alert.welcome');

    $customer = Customer::query()->whereEmail('jane@example.test')->first();
    expect($customer)->not->toBeNull()
        ->and($customer->is_activated)->toBeTrue()
        ->and(Auth::customer()?->getKey())->toBe($customer->getKey());
});

it('asks the customer to activate when the group needs approval', function(): void {
    CustomerGroup::getDefault()?->update(['approval' => 1]);

    $this->post(route('igniter.theme.account.register'), registration())
        ->assertRedirect(route('igniter.theme.account.login'))
        ->assertSessionHas('success', 'alert.check_email');

    $customer = Customer::query()->whereEmail('jane@example.test')->first();
    expect((bool)$customer->is_activated)->toBeFalse()
        ->and($customer->activation_code)->not->toBeEmpty();
});

it('refuses to register when registration is turned off', function(): void {
    Settings::set('allow_registration', 0);

    $this->from(route('igniter.theme.account.register'))
        ->post(route('igniter.theme.account.register'), registration())
        ->assertRedirect(route('igniter.theme.account.register'))
        ->assertSessionHasErrors('form');

    expect(Customer::query()->whereEmail('jane@example.test')->exists())->toBeFalse();
});

it('validates the register form', function(): void {
    Customer::factory()->create(['email' => 'taken@example.test']);

    $this->post(route('igniter.theme.account.register'), registration([
        'email' => 'taken@example.test',
        'password_confirmation' => 'different',
    ]))->assertSessionHasErrors(['email', 'password']);
});

it('activates an account from the emailed link', function(): void {
    $customer = Customer::factory()->create(['is_activated' => false]);
    $code = $customer->getActivationCode();

    $this->get(route('igniter.theme.account.register', ['code' => $code]))
        ->assertRedirect(route('igniter.theme.account.account'))
        ->assertSessionHas('success', 'alert.account_ready');

    expect($customer->fresh()->is_activated)->toBeTrue();
});
