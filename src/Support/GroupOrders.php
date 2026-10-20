<?php

declare(strict_types=1);

namespace Igniter\Storefront\Support;

use Igniter\Cart\Models\Order;
use Igniter\Local\Facades\Location;
use IgniterLabs\GroupOrder\Classes\Manager;
use IgniterLabs\GroupOrder\Models\GroupOrder;
use IgniterLabs\GroupOrder\Models\Guest;
use Throwable;

/**
 * Group ordering from the igniterlabs.grouporder extension: whether the current location
 * offers it, and the group order the visitor is hosting or has joined.
 */
class GroupOrders
{
    use ReadsKey;

    public const string MANAGER = Manager::class;

    /** Tests can switch the extension off; null means detect it. */
    public static ?bool $installed = null;

    public static function installed(): bool
    {
        return self::$installed ?? class_exists(self::MANAGER);
    }

    public static function manager(): Manager
    {
        return resolve(self::MANAGER);
    }

    /**
     * The table on the host's order, when the current visitor is a guest.
     */
    public static function hostTableId(): ?int
    {
        if (!self::installed()) {
            return null;
        }

        $manager = self::manager();
        $order = $manager->isCurrentGuest() ? $manager->getGroupOrder()?->hostOrder() : null;
        $id = (int)(is_object($order) ? ($order->dining_table_id ?? 0) : 0);

        return $id > 0 ? $id : null;
    }

    /**
     * Keep the host order's table in step with the one chosen in the order details.
     */
    public static function rememberHostTable(): void
    {
        if (!self::installed() || !DineInTables::installed()) {
            return;
        }

        $manager = self::manager();
        $order = $manager->isCurrentHost() ? $manager->getGroupOrder()?->hostOrder() : null;
        if (!$order instanceof Order) {
            return;
        }

        $tableId = Location::orderType() === 'dinein' ? (int)session('igniterlabs_dinein.table_id') : 0;
        // dining_table_id is added by the dine-in extension, not the Order model.
        $order->setAttribute('dining_table_id', $tableId > 0 ? $tableId : null);
        $order->saveQuietly();
    }

    /**
     * A guest has placed their order, so the group session should end for them.
     */
    public static function finishGuest(): void
    {
        if (self::installed() && self::manager()->isCurrentGuest()) {
            self::manager()->clearCurrent();
        }
    }

    /**
     * Shared with every page: whether a group order can be started here, and the active one.
     *
     * @return ($key is null ? array{canStart: bool, session: ?array}|null : mixed)
     */
    public static function present(?string $key = null, mixed $default = null): mixed
    {
        $present = self::sessionState();

        return self::read($present, $key, $default);
    }

    /**
     * @return array{canStart: bool, session: ?array}|null
     */
    protected static function sessionState(): ?array
    {
        if (!self::installed()) {
            return null;
        }

        try {
            $location = Location::current();
            // groupOrderEnabled() is a behaviour the extension adds to the Location model.
            if (!$location || !$location->methodExists('groupOrderEnabled') || !$location->groupOrderEnabled()) {
                return null;
            }

            $groupOrder = self::manager()->getGroupOrder();

            return [
                'canStart' => !$groupOrder instanceof GroupOrder,
                'session' => $groupOrder instanceof GroupOrder ? self::session($groupOrder) : null,
            ];
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }
    }

    /**
     * The group order as the host or a guest sees it.
     */
    public static function session(GroupOrder $groupOrder): array
    {
        $manager = self::manager();
        $isHost = $manager->isCurrentHost();
        $hostName = (string)$groupOrder->customer?->first_name;
        $hostOrder = $groupOrder->hostOrder();

        return [
            'id' => $groupOrder->getKey(),
            'hash' => $groupOrder->hash,
            'isHost' => $isHost,
            'isGuest' => $manager->isCurrentGuest(),
            'hostName' => $hostName,
            'title' => $isHost ? self::line('text_host_title', $groupOrder->getKey()) : self::line('text_guest_title', $hostName),
            'bannerTitle' => $isHost ? self::line('text_banner_host_title') : self::line('text_banner_guest_title', $hostName),
            'description' => self::line($isHost ? 'text_group_order_description' : 'text_group_order_guest_description'),
            'location' => (string)$groupOrder->location?->location_name,
            'payment' => self::paymentText($groupOrder, $isHost, $hostName),
            'deliveryAddress' => $hostOrder?->isDeliveryType() ? $groupOrder->formattedDeliveryAddress() : null,
            'locked' => $groupOrder->isLocked(),
            'deadline' => [
                'set' => $groupOrder->hasDeadline(),
                'passed' => $groupOrder->hasDeadline() && $groupOrder->isPastDeadline(),
                'remaining' => $groupOrder->hasDeadline() && !$groupOrder->isPastDeadline() ? $groupOrder->getTimeRemaining() : null,
            ],
            'inviteUrl' => route('igniter.theme.group-order.join', ['hash' => $groupOrder->hash]),
            'guestCount' => $groupOrder->guests->count(),
            'canLock' => $isHost && !$groupOrder->anyGuestsSubmitted(),
            'guests' => $isHost ? self::guests($groupOrder) : [],
            'totals' => $isHost ? self::totals($groupOrder) : null,
        ];
    }

    /**
     * Details for the join page, or why the group order cannot be joined.
     */
    public static function invite(?GroupOrder $groupOrder): array
    {
        if (!$groupOrder instanceof GroupOrder) {
            return ['state' => 'invalid', 'title' => self::line('text_invalid_link_title'), 'message' => self::line('text_invalid_link_description')];
        }

        $hostName = (string)$groupOrder->customer?->first_name;
        $state = match (true) {
            $groupOrder->isCanceled() => 'canceled',
            $groupOrder->isCompleted() => 'completed',
            $groupOrder->isLocked() => 'locked',
            $groupOrder->hasDeadline() && $groupOrder->isPastDeadline() => 'expired',
            default => 'open',
        };

        return [
            'state' => $state,
            'hash' => $groupOrder->hash,
            'hostName' => $hostName,
            'title' => $state === 'open' ? self::line('text_join_title', $hostName) : self::line('text_'.$state.'_title'),
            'message' => match ($state) {
                'open' => self::line('text_start_description'),
                'canceled' => self::line('text_canceled_description'),
                default => self::line('text_'.$state.'_description', $hostName),
            },
            'location' => (string)$groupOrder->location?->location_name,
            'payment' => match (true) {
                $groupOrder->hostPays() => self::line('text_invite_payment_host_pays'),
                $groupOrder->guestHasSpendingLimit() => self::line('text_invite_payment_spending_limit', Money::label($groupOrder->spending_limit)),
                default => self::line('text_invite_payment_guest_pays'),
            },
        ];
    }

    /**
     * A line from the group order extension's language file, with sprintf-style values.
     */
    public static function line(string $key, mixed ...$values): string
    {
        $line = lang('igniterlabs.grouporder::default.'.$key);

        return $values === [] ? $line : sprintf($line, ...$values);
    }

    protected static function paymentText(GroupOrder $groupOrder, bool $isHost, string $hostName): string
    {
        return match (true) {
            $groupOrder->hostPays() => $isHost ? self::line('text_host_pays') : self::line('text_guest_host_pays', $hostName),
            $groupOrder->guestHasSpendingLimit() => self::line($isHost ? 'text_host_spending_limit' : 'text_guest_spending_limit').Money::label($groupOrder->spending_limit),
            default => self::line($isHost ? 'text_host_guest_pays' : 'text_guest_pays'),
        };
    }

    /**
     * Guests and what each has ordered, for the host.
     */
    protected static function guests(GroupOrder $groupOrder): array
    {
        return $groupOrder->guests->map(fn(Guest $guest): array => [
            'id' => $guest->getKey(),
            'name' => $guest->name,
            'status' => match (true) {
                $guest->hasSubmittedOrder() => 'submitted',
                $guest->hasLeft() => 'left',
                default => 'joined',
            },
            'statusLabel' => self::line(match (true) {
                $guest->hasSubmittedOrder() => 'text_order_submitted',
                $guest->hasLeft() => 'text_left_order',
                default => 'text_still_adding_items',
            }),
            'items' => $guest->order ? $guest->order->menus->map(fn($menu): array => [
                'name' => (string)$menu->name,
                'qty' => (int)$menu->quantity,
                'price' => Money::label($menu->subtotal),
            ])->values()->all() : [],
            'subtotal' => $guest->order ? Money::label($guest->order->totals->where('code', 'subtotal')->value('value') ?? 0) : null,
            'canRemove' => !$guest->hasSubmittedOrder(),
        ])->values()->all();
    }

    /**
     * What guests have ordered and what the host covers through the split bill.
     */
    protected static function totals(GroupOrder $groupOrder): ?array
    {
        $guestTotal = 0.0;
        $contribution = 0.0;
        foreach ($groupOrder->guests as $guest) {
            $guestTotal += (float)($guest->order?->totals->where('code', 'subtotal')->value('value') ?? 0);
            $contribution += abs((float)($guest->order?->totals->where('code', 'splitBill')->value('value') ?? 0));
        }

        if ($guestTotal <= 0 && $contribution <= 0) {
            return null;
        }

        return [
            'guests' => Money::label($guestTotal),
            'contribution' => $contribution > 0 ? Money::label($contribution) : null,
        ];
    }
}
