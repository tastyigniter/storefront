<?php

declare(strict_types=1);

namespace Igniter\Storefront\Tests\Http\Middleware;

it('sends guests to the login page from account pages', function(): void {
    $this->get(route('igniter.theme.account.account'))
        ->assertRedirect(route('igniter.theme.account.login'));
});

it('sends signed-in customers away from the login page', function(): void {
    actingAsCustomer();

    $this->get(route('igniter.theme.account.login'))
        ->assertRedirect(route('igniter.theme.account.account'));
});
