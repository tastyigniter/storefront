<?php

declare(strict_types=1);

namespace Igniter\Storefront\Support;

use Igniter\Cart\Classes\CartManager;
use Igniter\Cart\Classes\OrderManager;
use Igniter\Cart\Models\Order;
use Igniter\Flame\Exception\ApplicationException;
use Igniter\Local\Models\Location as LocationModel;
use Igniter\Reservation\Models\DiningArea;
use Igniter\Reservation\Models\DiningTable;
use Igniter\Storefront\Storefront;
use IgniterLabs\DineIn\Models\Tab;
use IgniterLabs\DineIn\Support\AreaMenus;
use IgniterLabs\DineIn\Support\GuestCount;
use IgniterLabs\DineIn\Support\Seating;
use IgniterLabs\DineIn\Support\Settings;
use IgniterLabs\DineIn\Support\Tabs;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

/**
 * Dining areas and tables for the dine-in order type. The dine-in extension
 * reads the chosen table from the session when the order is placed.
 */
class DineInTables
{
    public static bool $enabled = false;

    public static function installed(): bool
    {
        return self::$enabled || class_exists('IgniterLabs\\DineIn\\OrderTypes\\DineIn');
    }

    public static function offered(LocationModel $location): bool
    {
        // hasDineIn() is added to the Location model by the dine-in extension, which method_exists() cannot see.
        return $location->methodExists('hasDineIn') && (bool)$location->hasDineIn();
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function present(?LocationModel $location): ?array
    {
        if (!$location instanceof LocationModel || !self::installed()) {
            return null;
        }

        /** @var Collection<int, DiningArea> $areas */
        $areas = DiningArea::query()->where('location_id', $location->getKey())->orderBy('name')->get();
        $areaIds = $areas->map(fn($area): int => (int)$area->getKey())->all();
        /** @var Collection<int, DiningTable> $tables */
        $tables = $areaIds === []
            ? new Collection
            : DiningTable::query()
                ->whereIn('dining_area_id', $areaIds)
                ->whereIsRoot()
                ->where('is_enabled', true)
                ->orderByDesc('priority')
                ->get();

        // A group-order guest sits at the host's table, not one they pick themselves.
        self::pinHostTable();

        $sessionArea = (int)session('igniterlabs_dinein.area_id');
        $sessionTable = (int)session('igniterlabs_dinein.table_id');
        $areaId = in_array($sessionArea, $areaIds, true) ? $sessionArea : null;
        if ($areaId === null && count($areaIds) === 1) {
            $areaId = $areaIds[0];
        }

        $tableIds = $tables
            ->filter(fn($table): bool => (int)$table->dining_area_id === $areaId)
            ->map(fn($table): int => (int)$table->getKey())
            ->all();
        $blocked = self::blockedIds($tables->map(fn($table): int => (int)$table->getKey())->all());
        $tableId = in_array($sessionTable, $tableIds, true) ? $sessionTable : null;

        return [
            'areas' => $areas->map(fn($area): array => [
                'id' => (int)$area->getKey(),
                'name' => (string)$area->name,
                'floorPlan' => is_array($area->floor_plan) && $area->floor_plan !== [] ? $area->floor_plan : null,
            ])->values()->all(),
            'tables' => $tables->map(function($table) use ($sessionTable, $blocked): array {
                $seat = is_array($table->seat_layout) ? $table->seat_layout : [];

                return [
                    'id' => (int)$table->getKey(),
                    'areaId' => (int)$table->dining_area_id,
                    'name' => (string)$table->name,
                    'guests' => self::guests((int)$table->min_capacity, (int)$table->max_capacity),
                    'min' => (int)$table->min_capacity,
                    'max' => (int)$table->max_capacity,
                    'available' => (int)$table->getKey() === $sessionTable || !in_array((int)$table->getKey(), $blocked, true),
                    'seat' => [
                        'x' => (float)($seat['x'] ?? 0),
                        'y' => (float)($seat['y'] ?? 0),
                        'rotation' => (float)($seat['rotation'] ?? 0),
                        'shape' => (string)($table->shape ?? 'rect'),
                    ],
                ];
            })->values()->all(),
            'areaId' => $areaId,
            'tableId' => $tableId,
            'seated' => self::hasSeatedTab((int)$tableId),
            'locked' => (bool)session('igniterlabs_dinein.locked'),
            'guestCount' => session()->has('igniterlabs_dinein.guest_count') ? (int)session('igniterlabs_dinein.guest_count') : null,
            'payLater' => class_exists(Settings::class) && Settings::payLater($location),
            'kitchenUrl' => Route::has('igniter.theme.dinein.kitchen') ? route('igniter.theme.dinein.kitchen') : null,
            'requestUrl' => Route::has('igniter.theme.dinein.request') ? route('igniter.theme.dinein.request') : null,
            'courseUrl' => Route::has('igniter.theme.dinein.course') ? route('igniter.theme.dinein.course') : null,
            'requests' => self::pendingRequests((int)$tableId),
            'payable' => ($bill = self::openBill()) instanceof Order,
            'billTotal' => $bill instanceof Order ? currency_format($bill->order_total) : null,
            'payOpenBill' => class_exists(Seating::class) && Seating::payingBill(),
            'payUrl' => Route::has('igniter.theme.dinein.pay') ? route('igniter.theme.dinein.pay') : null,
            'checkoutUrl' => Route::has('igniter.theme.checkout.checkout') ? route('igniter.theme.checkout.checkout') : null,
        ];
    }

    /**
     * Load the open tab bill into the basket so checkout can take payment normally.
     */
    public static function prepareOpenBillCheckout(): void
    {
        if (!self::installed() || !class_exists(Seating::class)) {
            return;
        }

        $order = self::openBill();
        if (!$order instanceof Order) {
            session()->forget(Seating::PAYING_BILL);

            return;
        }

        $cartManager = resolve(CartManager::class);
        if ($cartManager->getCart()->count() > 0) {
            session()->forget(Seating::PAYING_BILL);

            return;
        }

        $cartManager->getCart()->clearContent();
        $order->loadMissing(['menus.menu']);
        $cartManager->restoreWithOrderMenus($order->menus);
        resolve(OrderManager::class)->setCurrentOrderId((int)$order->getKey());
        session([Seating::PAYING_BILL => true]);
    }

    public static function remember(int $locationId, ?int $areaId, ?int $tableId, ?int $guestCount = null): bool
    {
        if (!self::installed()) {
            throw new ApplicationException(Storefront::lang('alert.no_dinein'));
        }

        $table = $areaId && $tableId ? DiningTable::query()
            ->where('id', $tableId)
            ->where('dining_area_id', $areaId)
            ->whereIsRoot()
            ->where('is_enabled', true)
            ->whereHas('dining_area', fn($query) => $query->where('location_id', $locationId))
            ->first() : null;

        if (!$table instanceof DiningTable) {
            throw new ApplicationException(Storefront::lang('alert.table_required'));
        }

        $sameTable = (int)session('igniterlabs_dinein.table_id') === (int)$table->getKey();
        if (!$sameTable && in_array((int)$table->getKey(), self::blockedIds([(int)$table->getKey()]), true)) {
            throw new ApplicationException(Storefront::lang('alert.table_unavailable'));
        }

        if ($guestCount !== null && class_exists(GuestCount::class) && !GuestCount::fits($table, $guestCount)) {
            throw new ApplicationException(Storefront::lang('alert.guest_count'));
        }

        $seating = [
            'igniterlabs_dinein.area_id' => (int)$areaId,
            'igniterlabs_dinein.table_id' => (int)$table->getKey(),
        ];
        if ($guestCount !== null) {
            $seating['igniterlabs_dinein.guest_count'] = $guestCount;
        }

        session($seating);
        self::bound($table);

        return true;
    }

    /**
     * Copy the host order's table into this session. Returns whether a table was applied.
     */
    public static function pinHostTable(): bool
    {
        $tableId = GroupOrders::hostTableId();
        if (!$tableId) {
            return false;
        }

        return self::adopt($tableId);
    }

    public static function adopt(int $tableId): bool
    {
        if (!self::installed() || $tableId < 1) {
            return false;
        }

        $table = DiningTable::query()->whereKey($tableId)->whereIsRoot()->where('is_enabled', true)->first();
        if (!$table instanceof DiningTable) {
            return false;
        }

        session([
            'igniterlabs_dinein.area_id' => (int)$table->dining_area_id,
            'igniterlabs_dinein.table_id' => (int)$table->getKey(),
        ]);
        self::bound($table);

        return true;
    }

    /**
     * Tables with an open tab, or a reservation covering now, cannot be chosen.
     *
     * @param list<int> $ids
     * @return list<int>
     */
    public static function blockedIds(array $ids): array
    {
        $ids = array_values(array_filter(array_map(intval(...), $ids)));
        if ($ids === []) {
            return [];
        }

        $free = DiningTable::query()->whereIn('dining_tables.id', $ids)->whereIsAvailableOn(now())->pluck('dining_tables.id')
            ->map(fn($id): int => (int)$id)
            ->all();
        $blocked = array_values(array_diff($ids, $free));

        if (class_exists(Tab::class) && Schema::hasTable('dinein_tabs')) {
            $seated = Tab::query()
                ->whereIn('dining_table_id', $ids)
                ->whereIn('status', [Tab::OPEN, Tab::BILL_REQUESTED])
                ->pluck('dining_table_id')
                ->map(fn($id): int => (int)$id)
                ->all();
            $blocked = array_values(array_unique([...$blocked, ...$seated]));
        }

        return $blocked;
    }

    protected static function bound(DiningTable $table): void
    {
        if (class_exists(AreaMenus::class)) {
            AreaMenus::apply((int)$table->dining_area_id);
        }

        if (class_exists(Tabs::class)) {
            Tabs::join((int)$table->getKey());
        }
    }

    /**
     * The table a dine-in order is served to, for the order receipt.
     *
     * @return array{name: string, area: ?string, guests: string}|null
     */
    public static function details(Order $order): ?array
    {
        if ($order->order_type !== 'dinein') {
            return null;
        }

        $id = (int)($order->getAttributes()['dining_table_id'] ?? 0);
        if ($id < 1) {
            return null;
        }

        // The dine-in extension adds the diningTable relation through the model's relation config.
        $model = self::installed() && $order->hasRelation('diningTable') ? $order->diningTable : null;
        $name = is_object($model) && isset($model->name) && $model->name !== '' ? (string)$model->name : '#'.$id;
        $area = is_object($model) && is_object($model->dining_area ?? null) ? (string)$model->dining_area->name : '';

        return [
            'name' => $name,
            'area' => $area !== '' ? $area : null,
            'guests' => self::partySize($order),
        ];
    }

    /**
     * How many people are on this bill. Capacity stays on the table picker.
     */
    protected static function partySize(Order $order): string
    {
        if (!class_exists(Tab::class) || !Schema::hasTable('dinein_tabs')) {
            return '';
        }

        $tab = Tab::forOrder((int)$order->getKey());
        $count = $tab instanceof Tab ? (int)$tab->guest_count : 0;

        return $count > 0 ? (string)$count : '';
    }

    protected static function hasSeatedTab(int $tableId): bool
    {
        return $tableId > 0
            && class_exists(Tab::class)
            && Schema::hasTable('dinein_tabs')
            && Tab::seatedAt($tableId) instanceof Tab;
    }

    /**
     * The open dine-in bill for this table, when it still needs payment.
     */
    public static function openBill(): ?Order
    {
        if (!class_exists(Tab::class) || !Schema::hasTable('dinein_tabs')) {
            return null;
        }

        $tab = Tab::seatedAt(class_exists(Seating::class) ? Seating::tableId() : (int)session('igniterlabs_dinein.table_id'));
        $order = $tab?->order;
        if (!$order instanceof Order || $order->isPaymentProcessed() || (float)$order->order_total <= 0) {
            return null;
        }

        return $order;
    }

    /**
     * Request kinds the table has asked for and staff have not acknowledged.
     *
     * @return list<string>
     */
    public static function pendingRequests(int $tableId): array
    {
        if (!self::hasSeatedTab($tableId)) {
            return [];
        }

        $tab = Tab::seatedAt($tableId);

        return $tab instanceof Tab
            ? $tab->requests()->whereNull('acknowledged_at')->pluck('kind')->unique()->values()->all()
            : [];
    }

    protected static function guests(int $min, int $max): string
    {
        if ($min < 1 && $max < 1) {
            return '';
        }

        return $min > 0 && $max > 0 && $min !== $max ? $min.'–'.$max : (string)max($min, $max);
    }

    public static function lockOrderType(string $orderType): array
    {
        if (!self::installed()) {
            return [];
        }

        if (self::locked() && $orderType !== 'dinein') {
            throw new ApplicationException(Storefront::lang('alert.dinein_locked'));
        }

        return self::locked()
            ? []
            : [
                (int)session(Seating::AREA) ?: null,
                (int)session(Seating::TABLE) ?: null,
            ];
    }

    public static function locked(): bool
    {
        return (bool)session('igniterlabs_dinein.locked');
    }

    public static function categoryIds(): ?array
    {
        return session('igniterlabs_dinein.category_ids');
    }
}
