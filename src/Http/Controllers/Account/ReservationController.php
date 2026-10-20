<?php

declare(strict_types=1);

namespace Igniter\Storefront\Http\Controllers\Account;

use Igniter\Reservation\Classes\BookingManager;
use Igniter\Reservation\Models\Reservation;
use Igniter\Storefront\Http\Concerns\PresentsStorefront;
use Igniter\Storefront\Http\Concerns\ResolvesStorefrontPage;
use Igniter\Storefront\Support\Hours;
use Igniter\Storefront\Support\PageMeta;
use Igniter\Storefront\Support\Pagination;
use Igniter\User\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ReservationController
{
    use PresentsStorefront;
    use ResolvesStorefrontPage;

    public function index(): Response
    {
        $page = Pagination::query(Auth::customer()->reservations()->latest('reservation_id'), fn(Reservation $reservation): array => [
            'hash' => $reservation->hash,
            'guests' => (int)$reservation->guest_num,
            'date' => optional($reservation->reserve_date)->toDateString() ?? (string)$reservation->reserve_date,
            'time' => Hours::clock($reservation->reserve_time),
            'status' => (string)($reservation->status_name ?? ''),
        ]);

        return Inertia::render('Account/Reservations', [
            'reservations' => $page['items'],
            'pagination' => $page['pagination'],
        ]);
    }

    public function show(string $hash): Response
    {
        $reservation = resolve(BookingManager::class)->getReservationByHash($hash, Auth::customer());
        abort_unless($reservation instanceof Reservation, 404);
        $reservation->load('location');

        return Inertia::render('Account/Reservation', [
            'reservation' => $this->reservationPayload($reservation),
            'meta' => PageMeta::make(
                PageMeta::line('meta.reservation_number', ['id' => $reservation->getKey()]),
                PageMeta::line('meta.account_reservation_description'),
            ),
        ]);
    }
}
