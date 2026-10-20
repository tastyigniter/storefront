<?php

declare(strict_types=1);

namespace Igniter\Storefront\Actions;

use Igniter\Flame\Exception\ApplicationException;
use Igniter\Local\Facades\Location;
use Igniter\Local\Models\Location as LocationModel;
use Igniter\Reservation\Classes\BookingManager;
use Igniter\Storefront\Storefront;
use Igniter\User\Facades\Auth;
use Igniter\User\Models\Customer;
use Illuminate\Support\Facades\Cache;

class PlaceReservation
{
    public function handle(array $data): mixed
    {
        $location = Location::currentOrDefault();
        if (!$location instanceof LocationModel) {
            throw new ApplicationException(Storefront::lang('alert.choose_location_table'));
        }

        $manager = resolve(BookingManager::class);
        $manager->useLocation($location);

        $customer = Auth::customer();

        $reservation = Cache::lock('storefront-booking-'.md5($location->getKey().$data['date'].$data['time']), 10)
            ->block(5, function() use ($manager, $data, $customer) {
                $reservation = $manager->loadReservation();

                return $manager->saveReservation($reservation, [
                    'sdateTime' => $data['date'].' '.$data['time'],
                    'guest' => $data['guest'],
                    'first_name' => $data['first_name'],
                    'last_name' => $data['last_name'],
                    'email' => $customer instanceof Customer ? $customer->email : $data['email'],
                    'telephone' => $data['telephone'],
                    'comment' => $data['comment'] ?? '',
                ]);
            });

        return redirect()->route('igniter.theme.booking.success', ['hash' => $reservation->hash]);
    }
}
