<?php

declare(strict_types=1);

namespace Igniter\Storefront\Tests\Http\Middleware;

use Igniter\Storefront\Http\Middleware\PreserveInertiaPayload;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

it('puts back the Inertia payload when other middleware changed it', function(): void {
    $request = Request::create('/order');
    $request->attributes->set(PreserveInertiaPayload::ATTRIBUTE, '{"component":"Order/Menu"}');

    $response = (new PreserveInertiaPayload)->handle($request, fn(): Response => new Response('{"component":"Order/Menu"}<div>banner</div>'));

    expect($response->getContent())->toBe('{"component":"Order/Menu"}');
});

it('leaves other responses alone', function(): void {
    $response = (new PreserveInertiaPayload)->handle(Request::create('/'), fn(): Response => new Response('<html></html>'));

    expect($response->getContent())->toBe('<html></html>');
});

it('keeps Inertia JSON clean through the whole stack', function(): void {
    currentLocation();

    $this->get(route('igniter.theme.contact'), ['X-Inertia' => 'true', 'X-Inertia-Version' => ''])
        ->assertOk()
        ->assertJsonPath('component', 'Contact/Index');
});
