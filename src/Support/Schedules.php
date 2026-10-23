<?php

declare(strict_types=1);

namespace Igniter\Storefront\Support;

use Carbon\Carbon;
use Igniter\Cart\Classes\AbstractOrderType;
use Igniter\Local\Facades\Location;
use Igniter\Local\Models\Location as LocationModel;
use Igniter\Reservation\Classes\BookingManager;
use Igniter\Storefront\Storefront;

class Schedules
{
    use ReadsKey;

    /**
     * Order types the current location offers, each with its own ASAP/later rules and timeslots.
     */
    public static function fulfilmentOptions(): array
    {
        $location = Location::current() ?? Location::currentOrDefault();
        if (!$location instanceof LocationModel) {
            return [];
        }

        return collect(Location::getActiveOrderTypes())->map(function(AbstractOrderType $orderType) use ($location): array {
            $code = $orderType->getCode();
            $schedule = $orderType->getSchedule();
            $restriction = $orderType->getScheduleRestriction();
            $leadMinutes = $location->shouldAddLeadTime($code) ? $orderType->getLeadTime() : 0;
            $slots = $restriction === AbstractOrderType::ASAP_ONLY
                ? []
                : $schedule->getTimeslot($orderType->getInterval(), null, $leadMinutes);

            return [
                'code' => $code,
                'label' => $orderType->getLabel(),
                'asap' => !$orderType->getMinimumFutureDays()
                    && $restriction !== AbstractOrderType::LATER_ONLY
                    && $schedule->isOpen(),
                'days' => collect($slots)->map(fn(mixed $times, string $date): array => [
                    'date' => $date,
                    'label' => Carbon::parse($date)->isToday()
                        ? Storefront::lang('schedule.today')
                        : (Carbon::parse($date)->isTomorrow() ? Storefront::lang('schedule.tomorrow') : Carbon::parse($date)->isoFormat('ddd D MMM')),
                    'times' => collect($times)->map(fn(mixed $time): string => Hours::clock($time))->unique()->values()->all(),
                ])->filter(fn(array $day): bool => $day['times'] !== [])->values()->all(),
            ];
        })->when(
            DineInTables::installed() && DineInTables::locked(),
            fn($options) => $options->where('code', 'dinein'),
        )->values()->all();
    }

    /**
     * What the customer picked for this order: type, time and delivery address.
     *
     * @return ($key is null ? array<string, mixed> : mixed)
     */
    public static function fulfilment(?string $key = null, mixed $default = null): mixed
    {
        $orderType = (string)(Location::orderType() ?: LocationModel::DELIVERY);
        $slot = (array)Location::getSession($orderType.'-timeslot', []);
        $dateTime = $slot['dateTime'] ?? null;
        $isAsap = !empty($slot['isAsap']) || !$dateTime || now()->isAfter($dateTime);
        $scheduled = $isAsap ? null : Carbon::parse($dateTime);

        $fulfilment = [
            'orderType' => $orderType,
            // Order types come from the current location; without one there are none to label.
            'orderTypeLabel' => (string)((Location::current() ? Location::getOrderType($orderType)?->getLabel() : null) ?? ucfirst($orderType)),
            'isAsap' => $isAsap,
            'date' => $scheduled?->toDateString(),
            'time' => $scheduled?->format('H:i'),
            'timeLabel' => $scheduled instanceof Carbon
                ? ($scheduled->isToday() ? Storefront::lang('schedule.today') : ($scheduled->isTomorrow() ? Storefront::lang('schedule.tomorrow') : $scheduled->isoFormat('ddd D MMM'))).', '.$scheduled->format('g:i A')
                : Storefront::lang('schedule.asap'),
            'address' => (string)Location::getSession('searchQuery'),
            'addressId' => Location::getSession('addressId') ? (int)Location::getSession('addressId') : null,
            'coverage' => DeliveryCoverage::present(),
        ];

        return self::read($fulfilment, $key, $default);
    }

    /**
     * @return array{minGuests: int, maxGuests: int, guests: int, date: string, minDate: string, maxDate: string, availableDates: list<string>, times: list<string>}
     */
    public static function booking(LocationModel $location, string $date, int $guests): array
    {
        $min = max(1, (int)$location->getMinReservationGuestCount());
        $max = max($min, (int)$location->getMaxReservationGuestCount());
        $guests = min($max, max($min, $guests));

        // Same range as the Orange booking form: Min/Max Advance Reservation Time, in days,
        // with days that have no reservation hours disabled.
        $minAdvance = max(0, (int)$location->getMinReservationAdvanceTime());
        $maxAdvance = max($minAdvance, (int)$location->getMaxReservationAdvanceTime());
        $start = Carbon::today()->addDays($minAdvance);
        $end = Carbon::today()->addDays($maxAdvance);

        $manager = resolve(BookingManager::class);
        $manager->useLocation($location);
        $schedule = $manager->getSchedule();

        $available = [];
        for ($cursor = $start->copy(); $cursor->lte($end); $cursor->addDay()) {
            if (count($schedule->forDate($cursor)) > 0) {
                $available[] = $cursor->toDateString();
            }
        }

        $day = Carbon::parse($date)->startOfDay();
        if (!in_array($day->toDateString(), $available, true)) {
            $day = Carbon::parse($available[0] ?? $start->toDateString());
        }

        $slots = collect($manager->makeTimeSlots($day));
        $booked = [];
        if ($slots->isNotEmpty()) {
            $booked = collect($manager->isTimeslotsFullyBookedOn($slots, $day, $guests))
                ->map(fn(mixed $slot): string => Hours::clock($slot))
                ->all();
        }

        $times = $slots
            ->map(fn(mixed $slot): string => Hours::clock($slot))
            ->unique()
            ->reject(fn(string $time): bool => in_array($time, $booked, true))
            ->values()
            ->all();

        return [
            'minGuests' => $min,
            'maxGuests' => $max,
            'guests' => $guests,
            'date' => $day->toDateString(),
            'minDate' => $start->toDateString(),
            'maxDate' => $end->toDateString(),
            'availableDates' => $available,
            'times' => $times,
        ];
    }

    public static function payments(LocationModel $location): array
    {
        return $location->listAvailablePayments()->map(fn($payment): array => [
            'code' => $payment->code,
            'name' => $payment->name,
            'description' => trim(html_entity_decode(strip_tags((string)$payment->description))),
        ])->values()->all();
    }
}
