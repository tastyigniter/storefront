<?php

declare(strict_types=1);

namespace Igniter\Storefront\Http\Controllers;

use Igniter\Flame\Exception\ApplicationException;
use Igniter\Local\Models\Location as LocationModel;
use Igniter\Reservation\Classes\BookingManager;
use Igniter\Reservation\Models\Reservation;
use Igniter\Storefront\Actions\PlaceReservation;
use Igniter\Storefront\Http\Concerns\PresentsStorefront;
use Igniter\Storefront\Http\Concerns\RedirectsFailures;
use Igniter\Storefront\Http\Concerns\ResolvesStorefrontPage;
use Igniter\Storefront\Http\Requests\PlaceReservationRequest;
use Igniter\Storefront\Storefront;
use Igniter\Storefront\Support\CancelConfirmation;
use Igniter\Storefront\Support\PageMeta;
use Igniter\Storefront\Support\Reviews;
use Igniter\Storefront\Support\Schedules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BookingController
{
    use PresentsStorefront;
    use RedirectsFailures;
    use ResolvesStorefrontPage;

    public function index(Request $request): Response|RedirectResponse
    {
        $location = $this->location();
        if (!$location instanceof LocationModel) {
            return redirect()->route('igniter.theme.home')->with('error', Storefront::lang('alert.choose_location_booking'));
        }

        return Inertia::render('Booking/Index', [
            'schedule' => Schedules::booking(
                $location,
                (string)$request->query('date', now()->toDateString()),
                (int)$request->query('guests', $location->getMinReservationGuestCount() ?: 2),
            ),
        ]);
    }

    public function store(PlaceReservationRequest $request, PlaceReservation $action): mixed
    {
        return $this->attempt(fn(): mixed => $action->handle($request->validated()));
    }

    public function success(string $hash): Response
    {
        $reservation = resolve(BookingManager::class)->getReservationByHash($hash);
        abort_unless($reservation instanceof Reservation, 404);
        $reservation->load('location');

        return Inertia::render('Booking/Success', [
            'reservation' => $this->reservationPayload($reservation),
            'review' => Reviews::present('reservation', $reservation),
            'meta' => PageMeta::make(
                PageMeta::line('meta.booking_confirmed', ['id' => $reservation->getKey()]),
                PageMeta::line('meta.booking_success_description'),
            ),
        ]);
    }

    public function cancel(Request $request, string $hash): mixed
    {
        return $this->attempt(function() use ($request, $hash) {
            $reservation = resolve(BookingManager::class)->getReservationByHash($hash);
            if (!$reservation instanceof Reservation) {
                throw new ApplicationException(Storefront::lang('alert.booking_not_found'));
            }

            $reservation->load('location');

            CancelConfirmation::authorize(
                $reservation->customer_id ? (int)$reservation->customer_id : null,
                $request->input('email'),
                $reservation->email,
                Storefront::lang('alert.booking_not_found'),
            );

            if ($reservation->isCanceled() || !$reservation->isCancelable() || !$reservation->markAsCanceled()) {
                throw new ApplicationException(lang('igniter.reservation::default.alert_cancel_failed'));
            }

            return back()->with('success', lang('igniter.reservation::default.alert_cancel_success'));
        });
    }
}
