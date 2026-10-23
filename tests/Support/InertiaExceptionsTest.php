<?php

declare(strict_types=1);

use Igniter\Flame\Exception\ApplicationException;

it('flashes application errors for inertia storefront visits', function(): void {
    Route::middleware('web')->get('/storefront-inertia-exception-test', function(): void {
        throw new ApplicationException('Table is required.');
    });

    $response = $this->withHeader('X-Inertia', 'true')
        ->get('/storefront-inertia-exception-test');

    $response->assertRedirect();
    expect(session('error'))->toBe('Table is required.');
});
