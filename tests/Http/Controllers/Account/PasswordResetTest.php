<?php

declare(strict_types=1);

namespace Igniter\Storefront\Tests\Http\Controllers\Account;

use Igniter\User\Models\Customer;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia;

beforeEach(function(): void {
    Mail::fake();
    config(['igniter-auth.timeboxDuration' => 0]);
});

it('shows the forgot password page', function(): void {
    $this->get(route('igniter.theme.account.forgot'))
        ->assertOk()
        ->assertInertia(fn(AssertableInertia $page): AssertableInertia => $page->component('Account/ForgotPassword'));
});

it('creates a reset code for a known email', function(): void {
    $customer = Customer::factory()->create(['email' => 'jane@example.test', 'status' => true]);

    $this->from(route('igniter.theme.account.forgot'))
        ->post(route('igniter.theme.account.forgot'), ['email' => 'jane@example.test'])
        ->assertRedirect(route('igniter.theme.account.forgot'))
        ->assertSessionHas('success');

    expect($customer->fresh()->reset_code)->not->toBeEmpty();
});

it('gives the same answer for an unknown email', function(): void {
    $this->from(route('igniter.theme.account.forgot'))
        ->post(route('igniter.theme.account.forgot'), ['email' => 'nobody@example.test'])
        ->assertSessionHas('success', 'alert.reset_link_sent');
});

it('validates the forgot password form', function(): void {
    $this->post(route('igniter.theme.account.forgot'), ['email' => 'not-an-email'])
        ->assertSessionHasErrors('email');
});

it('shows the reset password page', function(): void {
    $this->get(route('igniter.theme.account.reset', ['code' => 'abc']))
        ->assertOk()
        ->assertInertia(fn(AssertableInertia $page): AssertableInertia => $page
            ->component('Account/ResetPassword')
            ->where('code', 'abc'));
});

it('resets the password with a valid code', function(): void {
    $customer = Customer::factory()->create();
    $code = $customer->resetPassword();

    $this->post(route('igniter.theme.account.reset', ['code' => $code]), [
        'password' => 'new-secret',
        'password_confirmation' => 'new-secret',
    ])->assertRedirect(route('igniter.theme.account.login'))
        ->assertSessionHas('success', 'alert.password_updated');

    expect(Hash::check('new-secret', $customer->fresh()->password))->toBeTrue();
});

it('rejects an unknown reset code', function(): void {
    $this->from(route('igniter.theme.account.reset', ['code' => 'missing']))
        ->post(route('igniter.theme.account.reset', ['code' => 'missing']), [
            'password' => 'new-secret',
            'password_confirmation' => 'new-secret',
        ])->assertSessionHasErrors('password');
});
