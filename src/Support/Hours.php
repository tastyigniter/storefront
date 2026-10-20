<?php

declare(strict_types=1);

namespace Igniter\Storefront\Support;

use DateTimeInterface;
use Igniter\Local\Models\Location;
use Igniter\Local\Models\WorkingHour;
use Igniter\Storefront\Storefront;

class Hours
{
    public static function today(Location $location): string
    {
        $periods = self::periodsFor($location, now()->dayOfWeekIso - 1);

        return $periods === [] ? self::closed() : implode(', ', $periods);
    }

    /**
     * @return list<array{day: string, hours: string}>
     */
    public static function week(Location $location): array
    {
        $days = self::weekDays();
        $week = [];
        foreach ($days as $index => $day) {
            $periods = self::periodsFor($location, (int)$index);
            $week[] = [
                'day' => $day,
                'hours' => $periods === [] ? self::closed() : implode(', ', $periods),
            ];
        }

        // Open around the clock every day: one row instead of seven.
        if (collect($week)->every(fn(array $day): bool => $day['hours'] === self::allDay())) {
            return [['day' => $days[0].'–'.$days[count($days) - 1], 'hours' => self::allDay()]];
        }

        return $week;
    }

    /**
     * @return list<string>
     */
    protected static function periodsFor(Location $location, int $weekday): array
    {
        if (!$location->relationLoaded('working_hours')) {
            $location->load('working_hours');
        }

        return $location->working_hours
            ->where('type', WorkingHour::OPENING)
            ->where('weekday', $weekday)
            ->filter(fn(WorkingHour $hour): bool => (bool)$hour->status)
            ->map(fn(WorkingHour $hour): string => self::isAllDay($hour)
                ? self::allDay()
                : self::clock($hour->opening_time).' – '.self::clock($hour->closing_time))
            ->values()
            ->all();
    }

    /**
     * Short day names from the language file, Monday first.
     *
     * @return list<string>
     */
    protected static function weekDays(): array
    {
        $days = array_map(trim(...), explode(',', Storefront::lang('calendar.weekdays')));

        return count($days) === 7 ? $days : WorkingHour::$weekDays;
    }

    protected static function closed(): string
    {
        return Storefront::lang('hours.closed');
    }

    protected static function allDay(): string
    {
        return Storefront::lang('hours.all_day');
    }

    /**
     * 00:00 to 23:59 (or to midnight) covers the whole day.
     */
    protected static function isAllDay(WorkingHour $hour): bool
    {
        return self::clock($hour->opening_time) === '00:00'
            && in_array(self::clock($hour->closing_time), ['23:59', '00:00'], true);
    }

    public static function clock(mixed $time): string
    {
        if ($time instanceof DateTimeInterface) {
            return $time->format('H:i');
        }

        return substr((string)$time, 11, 5) ?: substr((string)$time, 0, 5);
    }
}
