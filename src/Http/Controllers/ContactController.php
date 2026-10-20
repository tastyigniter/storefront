<?php

declare(strict_types=1);

namespace Igniter\Storefront\Http\Controllers;

use Igniter\Storefront\Actions\ManageAccount;
use Igniter\Storefront\Http\Concerns\RedirectsFailures;
use Igniter\Storefront\Http\Concerns\ResolvesStorefrontPage;
use Igniter\Storefront\Http\Requests\ContactRequest;
use Igniter\Storefront\Storefront;
use Inertia\Inertia;
use Inertia\Response;

class ContactController
{
    use RedirectsFailures;
    use ResolvesStorefrontPage;

    public function index(): Response
    {
        return Inertia::render('Contact/Index', [
            'subjects' => [
                Storefront::lang('contact.subject_general'),
                Storefront::lang('contact.subject_comment'),
                Storefront::lang('contact.subject_technical'),
            ],
        ]);
    }

    public function store(ContactRequest $request, ManageAccount $action): mixed
    {
        return $this->attempt(function() use ($request, $action) {
            $action->contact($request->validated());

            return back()->with('success', Storefront::lang('alert.contact_sent'));
        });
    }
}
