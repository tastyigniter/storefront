<?php

declare(strict_types=1);

namespace Igniter\Storefront\Support;

use Igniter\Flame\Geolite\Model\Location as UserLocation;
use Igniter\Local\Facades\Location;
use Igniter\Local\Models\Location as LocationModel;
use Igniter\Local\Models\LocationArea;
use Igniter\Storefront\Storefront;
use Throwable;

/**
 * Whether the customer's delivery address falls inside one of the location's delivery areas.
 * Checked once when the address is set (address-based areas call the geocoder) and kept in
 * the session, so pages can explain coverage without re-checking on every request.
 */
class DeliveryCoverage
{
    public const string COVERED = 'covered';

    public const string OUTSIDE = 'outside';

    protected const string SESSION_KEY = 'storefrontCoverage';

    public static function check(LocationModel $location, ?UserLocation $position): ?string
    {
        $area = $position?->hasCoordinates() && $location->delivery_areas->isNotEmpty()
            ? $location->searchDeliveryArea($position->getCoordinates())
            : null;

        return self::record($location, $position, $area);
    }

    /**
     * Store the result of an area search that has already been done.
     */
    public static function record(LocationModel $location, ?UserLocation $position, ?LocationArea $area): ?string
    {
        if (!$position?->hasCoordinates() || $location->delivery_areas->isEmpty()) {
            Location::forgetSession(self::SESSION_KEY);

            return null;
        }

        if ($area instanceof LocationArea) {
            Location::updateNearbyArea($area);
            $status = self::COVERED;
        } else {
            Location::clearCoveredArea();
            $status = self::OUTSIDE;
        }

        Location::putSession(self::SESSION_KEY, $status);

        return $status;
    }

    /**
     * @return null|array{status: string, area: ?string, conditions: list<string>, collection: bool}
     */
    public static function present(): ?array
    {
        $status = Location::getSession(self::SESSION_KEY);
        if (!in_array($status, [self::COVERED, self::OUTSIDE], true) || !Location::orderTypeIsDelivery()) {
            return null;
        }

        $location = Location::current() ?? Location::currentOrDefault();
        $conditions = [];
        $area = null;

        if ($status === self::COVERED) {
            try {
                $covered = Location::coveredArea();
                $area = (string)$covered->name ?: null;
                $conditions = collect($covered->getConditionLabels())
                    ->map(fn(string $label): string => trim(html_entity_decode(strip_tags($label))))
                    ->filter()
                    ->values()
                    ->all();
            } catch (Throwable) {
                // The area was removed since it was checked; still say the address is covered.
            }
        }

        return [
            'status' => $status,
            'area' => $area,
            'conditions' => $conditions,
            'collection' => (bool)$location?->hasCollection(),
        ];
    }

    /**
     * A flash level and one-line message for after a search, or null when no delivery areas are set up.
     *
     * @return null|array{0: string, 1: string}
     */
    public static function message(): ?array
    {
        $coverage = self::present();
        $name = (Location::current() ?? Location::currentOrDefault())?->location_name;

        return match ($coverage['status'] ?? null) {
            self::COVERED => ['success', Storefront::lang('alert.coverage_covered', ['location' => $name])
                .($coverage['conditions'] ? ' '.implode(' · ', $coverage['conditions']).'.' : '')],
            self::OUTSIDE => ['error', Storefront::lang('alert.coverage_outside', ['location' => $name])
                .($coverage['collection'] ? Storefront::lang('alert.coverage_collection') : '')],
            default => null,
        };
    }
}
