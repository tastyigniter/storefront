<?php

declare(strict_types=1);

namespace Igniter\Storefront\Tests\Http;

require_once __DIR__.'/../Fakes/GroupOrder.php';

use Igniter\Cart\Models\Order;
use Igniter\Local\Facades\Location;
use Igniter\Local\Models\Location as LocationModel;
use Igniter\Reservation\Models\DiningArea;
use Igniter\Reservation\Models\DiningTable;
use Igniter\Storefront\Support\DineInTables;
use Igniter\Storefront\Support\GroupOrders;
use IgniterLabs\GroupOrder\Actions\FakeAction;
use IgniterLabs\GroupOrder\Actions\JoinGroupOrder;
use IgniterLabs\GroupOrder\Actions\LeaveGroupOrder;
use IgniterLabs\GroupOrder\Actions\RemoveGuest;
use IgniterLabs\GroupOrder\Actions\SendInvite;
use IgniterLabs\GroupOrder\Actions\StartGroupOrder;
use IgniterLabs\GroupOrder\Classes\Manager;
use IgniterLabs\GroupOrder\Models\GroupOrder;
use IgniterLabs\GroupOrder\Models\Guest;
use Inertia\Testing\AssertableInertia;
use RuntimeException;

/**
 * Whether locations offer group ordering in these tests (the extension adds groupOrderEnabled()).
 */
final class GroupOrderSwitch
{
    public static bool $enabled = false;
}

function groupKey(string $key): string
{
    return 'igniterlabs.grouporder::default.'.$key;
}

function fakeManager(?GroupOrder $groupOrder = null, bool $host = true, ?Guest $guest = null): Manager
{
    $manager = new Manager;
    $manager->groupOrder = $groupOrder;
    $manager->host = $host;
    $manager->guest = $guest;
    app()->instance(Manager::class, $manager);

    return $manager;
}

function fakeGroupOrder(array $attributes = []): GroupOrder
{
    $groupOrder = new GroupOrder(array_merge([
        'location' => Location::current(),
        'customer' => (object)['first_name' => 'Cheryl'],
    ], $attributes));

    return GroupOrder::$records[$groupOrder->hash] = $groupOrder;
}

function guestOrder(float $subtotal, float $splitBill = 0): object
{
    return (object)[
        'menus' => collect([(object)['name' => 'Jollof rice', 'quantity' => 2, 'subtotal' => $subtotal]]),
        'totals' => collect([
            (object)['code' => 'subtotal', 'value' => $subtotal],
            (object)['code' => 'splitBill', 'value' => -$splitBill],
        ]),
    ];
}

beforeEach(function(): void {
    // Model extensions are cleared between tests, so add the extension's method each time.
    LocationModel::extend(function(LocationModel $model): void {
        $model->addDynamicMethod('groupOrderEnabled', fn(): bool => GroupOrderSwitch::$enabled);
    });

    GroupOrderSwitch::$enabled = true;
    $this->location = currentLocation();
    fakeManager();
});

afterEach(function(): void {
    GroupOrders::$installed = null;
    GroupOrderSwitch::$enabled = false;
    DineInTables::$enabled = false;
    GroupOrder::$records = [];
    FakeAction::$calls = [];
    FakeAction::$throw = null;
    FakeAction::$result = null;
});

// Shared state

it('offers a group order where the location allows it', function(): void {
    expect(GroupOrders::installed())->toBeTrue()
        ->and(GroupOrders::present())->toBe(['canStart' => true, 'session' => null]);
});

it('offers nothing without the extension', function(): void {
    GroupOrders::$installed = false;

    expect(GroupOrders::present())->toBeNull()
        ->and(GroupOrders::hostTableId())->toBeNull();

    GroupOrders::rememberHostTable();
    expect(FakeAction::$calls)->toBe([]);
});

it('offers nothing where group ordering is off or there is no location', function(): void {
    GroupOrderSwitch::$enabled = false;
    expect(GroupOrders::present())->toBeNull();

    (fn() => $this->model = null)->call(resolve('location'));
    Location::resetSession();
    LocationModel::query()->delete();
    expect(GroupOrders::present())->toBeNull();
});

it('shares nothing when the group order cannot be read', function(): void {
    app()->instance(Manager::class, new class extends Manager
    {
        public function getGroupOrder(): ?GroupOrder
        {
            throw new RuntimeException('database down');
        }
    });

    expect(GroupOrders::present())->toBeNull();
});

it('shows the host every guest, their orders and the totals', function(): void {
    $groupOrder = fakeGroupOrder([
        'deadline' => 'remaining',
        'deliveryAddress' => '1 High Street',
        'host' => new class
        {
            public function isDeliveryType(): bool
            {
                return true;
            }
        },
        'guests' => collect([
            new Guest(1, 'Ada', 'submitted', guestOrder(12, 5)),
            new Guest(2, 'Bob', 'left'),
            new Guest(3, 'Cy'),
        ]),
    ]);
    fakeManager($groupOrder);

    $session = GroupOrders::present('session');

    expect($session)->toMatchArray([
        'isHost' => true,
        'isGuest' => false,
        'hostName' => 'Cheryl',
        'title' => groupKey('text_host_title'),
        'bannerTitle' => groupKey('text_banner_host_title'),
        'payment' => groupKey('text_host_guest_pays'),
        'deliveryAddress' => '1 High Street',
        'deadline' => ['set' => true, 'passed' => false, 'remaining' => '25 minutes'],
        'inviteUrl' => route('igniter.theme.group-order.join', ['hash' => 'abc123']),
        'guestCount' => 3,
        'canLock' => true,
    ])
        ->and(array_column($session['guests'], 'status'))->toBe(['submitted', 'left', 'joined'])
        ->and($session['guests'][0]['items'][0])->toMatchArray(['name' => 'Jollof rice', 'qty' => 2])
        ->and($session['guests'][0]['canRemove'])->toBeFalse()
        ->and($session['guests'][2]['subtotal'])->toBeNull()
        ->and($session['totals']['contribution'])->not->toBeNull();
});

it('leaves out totals until guests have ordered', function(): void {
    fakeManager(fakeGroupOrder(['payment' => 'host', 'guests' => collect([new Guest])]));

    expect(GroupOrders::present('session'))->toMatchArray([
        'payment' => groupKey('text_host_pays'),
        'deliveryAddress' => null,
        'totals' => null,
    ]);
});

it('shows a guest the host and who pays', function(): void {
    fakeManager(fakeGroupOrder(['payment' => 'host', 'deadline' => 'passed']), false, new Guest);
    $session = GroupOrders::present('session');

    expect($session)->toMatchArray([
        'isHost' => false,
        'isGuest' => true,
        'title' => groupKey('text_guest_title'),
        'payment' => groupKey('text_guest_host_pays'),
        'deadline' => ['set' => true, 'passed' => true, 'remaining' => null],
        'guests' => [],
        'totals' => null,
    ]);

    fakeManager(fakeGroupOrder(['payment' => 'limit', 'spending_limit' => 10.0]), false, new Guest);
    expect(GroupOrders::present('session.payment'))->toStartWith(groupKey('text_guest_spending_limit'));
});

it('explains invite links that cannot be joined', function(string $state, array $attributes): void {
    expect(GroupOrders::invite(fakeGroupOrder($attributes)))->toMatchArray([
        'state' => $state,
        'title' => groupKey('text_'.$state.'_title'),
    ]);
})->with([
    ['canceled', ['canceled' => true]],
    ['completed', ['completed' => true]],
    ['locked', ['locked' => true]],
    ['expired', ['deadline' => 'passed']],
]);

it('describes an open invite and who pays', function(): void {
    expect(GroupOrders::invite(fakeGroupOrder()))->toMatchArray(['state' => 'open', 'hostName' => 'Cheryl', 'payment' => groupKey('text_invite_payment_guest_pays')])
        ->and(GroupOrders::invite(fakeGroupOrder(['payment' => 'host']))['payment'])->toBe(groupKey('text_invite_payment_host_pays'))
        ->and(GroupOrders::invite(fakeGroupOrder(['payment' => 'limit', 'spending_limit' => 5.0]))['payment'])->toBe(groupKey('text_invite_payment_spending_limit'))
        ->and(GroupOrders::invite(null)['state'])->toBe('invalid');
});

// Starting

it('starts a group order and goes to the menu', function(): void {
    actingAsCustomer();

    $this->post(route('igniter.theme.group-order.start'), ['split_bill' => 1, 'spending_limit' => 15])
        ->assertRedirect(route('igniter.theme.order'));

    expect(FakeAction::$calls[0][0])->toBe(StartGroupOrder::class)
        ->and(FakeAction::$calls[0][1][2])->toBe(['split_bill' => 1, 'spending_limit' => 15])
        ->and(resolve(Manager::class)->groupOrder)->toBeInstanceOf(GroupOrder::class);
});

it('will not start a second group order', function(): void {
    actingAsCustomer();
    fakeManager(fakeGroupOrder());

    $this->from(route('igniter.theme.order'))
        ->post(route('igniter.theme.group-order.start'), ['split_bill' => 0])
        ->assertSessionHasErrors(['form' => groupKey('alert_group_order_exists')]);
});

it('validates the start form', function(): void {
    actingAsCustomer();

    $this->post(route('igniter.theme.group-order.start'), ['split_bill' => 3])->assertSessionHasErrors('split_bill');
});

// Joining

it('shows the join page', function(): void {
    fakeGroupOrder();

    $this->get(route('igniter.theme.group-order.join', ['hash' => 'abc123']))
        ->assertOk()
        ->assertInertia(fn(AssertableInertia $page): AssertableInertia => $page
            ->component('GroupOrder/Join')
            ->where('invite.state', 'open')
            ->where('guest.email', ''));

    $this->get(route('igniter.theme.group-order.join', ['hash' => 'missing']))
        ->assertInertia(fn(AssertableInertia $page): AssertableInertia => $page->where('invite.state', 'invalid'));
});

it('joins a group order as a guest', function(): void {
    fakeGroupOrder();

    $this->post(route('igniter.theme.group-order.join.store', ['hash' => 'abc123']), [
        'first_name' => 'Ada',
        'last_name' => 'Guest',
        'email' => 'ada@example.test',
    ])->assertRedirect(route('igniter.theme.order', ['ghash' => 'abc123']))
        ->assertSessionHas('success', groupKey('alert_group_order_joined'));

    expect(FakeAction::$calls[0][0])->toBe(JoinGroupOrder::class)
        ->and(resolve(Manager::class)->session)->toBe(['guestId' => 7]);
});

it('refuses invite links that are gone, locked or canceled', function(string $hash, array $attributes, string $alert): void {
    fakeGroupOrder(['hash' => 'abc123'] + $attributes);

    $this->from('/')
        ->post(route('igniter.theme.group-order.join.store', ['hash' => $hash]), ['first_name' => 'A', 'last_name' => 'B', 'email' => 'a@example.test'])
        ->assertSessionHasErrors(['form' => groupKey($alert)]);
})->with([
    ['missing', [], 'alert_invalid_invite_link'],
    ['abc123', ['locked' => true], 'alert_group_order_locked'],
    ['abc123', ['canceled' => true], 'alert_group_order_canceled'],
    ['abc123', ['location' => null], 'alert_location_required'],
]);

it('seats a joining guest at the host table', function(): void {
    DineInTables::$enabled = true;
    $area = DiningArea::factory()->create(['location_id' => $this->location->getKey(), 'name' => 'Patio']);
    $table = DiningTable::factory()->create([
        'dining_area_id' => $area->getKey(),
        'name' => 'Window',
        'is_enabled' => true,
    ]);
    fakeGroupOrder(['host' => (object)['dining_table_id' => $table->getKey()]]);

    $this->post(route('igniter.theme.group-order.join.store', ['hash' => 'abc123']), [
        'first_name' => 'Ada',
        'last_name' => 'Guest',
        'email' => 'ada@example.test',
    ])->assertRedirect();

    expect(session('igniterlabs_dinein.table_id'))->toBe((int)$table->getKey())
        ->and(session('igniterlabs_dinein.area_id'))->toBe((int)$area->getKey())
        ->and(DineInTables::adopt(0))->toBeFalse()
        ->and(DineInTables::adopt(999999))->toBeFalse();
});

it('keeps a guest on the host table when the menu loads', function(): void {
    DineInTables::$enabled = true;
    $area = DiningArea::factory()->create(['location_id' => $this->location->getKey(), 'name' => 'Patio']);
    $table = DiningTable::factory()->create([
        'dining_area_id' => $area->getKey(),
        'name' => 'Window',
        'is_enabled' => true,
    ]);
    session(['igniterlabs_dinein.table_id' => 999, 'igniterlabs_dinein.area_id' => 999]);
    fakeManager(fakeGroupOrder(['host' => (object)['dining_table_id' => $table->getKey()]]), false, new Guest);

    expect(DineInTables::present($this->location)['tableId'])->toBe((int)$table->getKey())
        ->and(session('igniterlabs_dinein.area_id'))->toBe((int)$area->getKey());
});

it('saves the host table onto the group order', function(): void {
    DineInTables::$enabled = true;
    $order = new class
    {
        public ?int $dining_table_id = null;

        public int $saves = 0;

        public function saveQuietly(): void
        {
            $this->saves++;
        }
    };
    fakeManager(fakeGroupOrder(['host' => $order]));
    Location::updateOrderType('dinein');
    session(['igniterlabs_dinein.table_id' => 4]);

    GroupOrders::rememberHostTable();

    expect($order->dining_table_id)->toBe(4)
        ->and($order->saves)->toBe(1);

    Location::updateOrderType('delivery');
    GroupOrders::rememberHostTable();

    expect($order->dining_table_id)->toBeNull()
        ->and($order->saves)->toBe(2);
});

it('clears the group session when a guest finishes checkout', function(): void {
    $manager = fakeManager(fakeGroupOrder(), false, new Guest);
    $customer = actingAsCustomer();
    $order = Order::factory()->create([
        'customer_id' => $customer->getKey(),
        'location_id' => $this->location->getKey(),
        'order_total' => 10,
    ]);

    $this->get(route('igniter.theme.checkout.success', ['hash' => $order->hash]))->assertOk();

    expect($manager->groupOrder)->toBeNull();
});

it('validates the join form', function(): void {
    $this->post(route('igniter.theme.group-order.join.store', ['hash' => 'abc123']), [])
        ->assertSessionHasErrors(['first_name', 'last_name', 'email']);
});

// Managing

it('sends an email invite', function(): void {
    fakeManager(fakeGroupOrder());

    $this->from(route('igniter.theme.order'))
        ->post(route('igniter.theme.group-order.invite'), ['email' => 'friend@example.test'])
        ->assertSessionHas('success', groupKey('alert_invite_sent'));

    expect(FakeAction::$calls[0])->toMatchArray([SendInvite::class]);
});

it('will not email an invite for a guest', function(): void {
    fakeManager(fakeGroupOrder(), false, new Guest);

    $this->from(route('igniter.theme.order'))
        ->post(route('igniter.theme.group-order.invite'), ['email' => 'friend@example.test'])
        ->assertSessionHasErrors(['form' => groupKey('alert_host_only')]);

    expect(FakeAction::$calls)->toBe([]);
});

it('needs an active group order to invite', function(): void {
    $this->from(route('igniter.theme.order'))
        ->post(route('igniter.theme.group-order.invite'), ['email' => 'friend@example.test'])
        ->assertSessionHasErrors(['form' => groupKey('alert_group_order_required')]);
});

it('locks and unlocks for the host only', function(): void {
    fakeManager(fakeGroupOrder());

    $this->from('/order')->post(route('igniter.theme.group-order.lock'))->assertSessionHas('success', groupKey('alert_group_order_locked'));
    $this->from('/order')->post(route('igniter.theme.group-order.unlock'))->assertSessionHas('success', groupKey('alert_group_order_unlocked'));

    fakeManager(fakeGroupOrder(), false, new Guest);
    $this->from('/order')->post(route('igniter.theme.group-order.lock'))->assertSessionHasErrors(['form' => groupKey('alert_host_only')]);
});

it('cancels the group order for the host', function(): void {
    $manager = fakeManager(fakeGroupOrder());

    $this->delete(route('igniter.theme.group-order.destroy'))
        ->assertRedirect(route('igniter.theme.order'))
        ->assertSessionHas('success', groupKey('alert_group_order_canceled'));

    expect($manager->groupOrder)->toBeNull();
});

it('lets a guest leave', function(): void {
    fakeManager(fakeGroupOrder(), false, new Guest);

    $this->delete(route('igniter.theme.group-order.destroy'))->assertSessionHas('success', groupKey('alert_left_group_order'));

    expect(FakeAction::$calls[0][0])->toBe(LeaveGroupOrder::class);
});

it('cannot leave without a guest record', function(): void {
    fakeManager(fakeGroupOrder(), false);

    $this->from('/order')->delete(route('igniter.theme.group-order.destroy'))->assertSessionHasErrors(['form' => groupKey('alert_guest_not_found')]);
});

it('removes a guest', function(): void {
    fakeManager(fakeGroupOrder(['guests' => collect([new Guest(5)])]));

    $this->from('/order')->delete(route('igniter.theme.group-order.guests.destroy', ['guestId' => 5]))->assertSessionHas('success', groupKey('alert_guest_removed'));
    expect(FakeAction::$calls[0][0])->toBe(RemoveGuest::class);

    $this->from('/order')->delete(route('igniter.theme.group-order.guests.destroy', ['guestId' => 9]))->assertSessionHasErrors(['form' => groupKey('alert_guest_not_found')]);
});

it('leaves the host table alone when there is no host order', function(): void {
    DineInTables::$enabled = true;
    fakeManager(fakeGroupOrder(['host' => null]));

    GroupOrders::rememberHostTable();

    expect(GroupOrders::hostTableId())->toBeNull();
    DineInTables::$enabled = false;
});
