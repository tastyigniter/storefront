<?php

declare(strict_types=1);

namespace Igniter\Storefront\Support;

use Igniter\Cart\Models\Order;
use Igniter\Flame\Exception\ApplicationException;
use Igniter\Local\Models\Location as LocationModel;
use Igniter\Reservation\Models\DiningArea;
use Igniter\Reservation\Models\DiningTable;
use Igniter\Storefront\Storefront;
use Illuminate\Database\Eloquent\Collection;

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
     * @return array{areas: list<array{id: int, name: string}>, tables: list<array{id: int, areaId: int, name: string, guests: string}>, areaId: int|null, tableId: int|null}|null
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

        return [
            'areas' => $areas->map(fn($area): array => [
                'id' => (int)$area->getKey(),
                'name' => (string)$area->name,
            ])->values()->all(),
            'tables' => $tables->map(fn($table): array => [
                'id' => (int)$table->getKey(),
                'areaId' => (int)$table->dining_area_id,
                'name' => (string)$table->name,
                'guests' => self::guests((int)$table->min_capacity, (int)$table->max_capacity),
            ])->values()->all(),
            'areaId' => $areaId,
            'tableId' => in_array($sessionTable, $tableIds, true) ? $sessionTable : null,
        ];
    }

    public static function remember(int $locationId, ?int $areaId, ?int $tableId): bool
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

        session([
            'igniterlabs_dinein.area_id' => (int)$areaId,
            'igniterlabs_dinein.table_id' => (int)$table->getKey(),
        ]);

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

        return true;
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
            'guests' => is_object($model) ? self::guests((int)($model->min_capacity ?? 0), (int)($model->max_capacity ?? 0)) : '',
        ];
    }

    protected static function guests(int $min, int $max): string
    {
        if ($min < 1 && $max < 1) {
            return '';
        }

        return $min > 0 && $max > 0 && $min !== $max ? $min.'–'.$max : (string)max($min, $max);
    }
}
