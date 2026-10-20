<?php

declare(strict_types=1);

namespace Igniter\Storefront\Http\Controllers;

use Igniter\Flame\Exception\ApplicationException;
use Igniter\Local\Facades\Location;
use Igniter\Local\Models\Location as LocationModel;
use Igniter\Storefront\Http\Concerns\RedirectsFailures;
use Igniter\Storefront\Http\Concerns\ResolvesStorefrontPage;
use Igniter\Storefront\Support\DineInTables;
use Igniter\Storefront\Support\GroupOrders;
use Igniter\Storefront\Support\PageMeta;
use Igniter\Storefront\Support\StorefrontRoutes;
use Igniter\User\Facades\Auth;
use IgniterLabs\GroupOrder\Actions\CancelGroupOrder;
use IgniterLabs\GroupOrder\Actions\JoinGroupOrder;
use IgniterLabs\GroupOrder\Actions\LeaveGroupOrder;
use IgniterLabs\GroupOrder\Actions\LockGroupOrder;
use IgniterLabs\GroupOrder\Actions\RemoveGuest;
use IgniterLabs\GroupOrder\Actions\SendInvite;
use IgniterLabs\GroupOrder\Actions\StartGroupOrder;
use IgniterLabs\GroupOrder\Actions\UnlockGroupOrder;
use IgniterLabs\GroupOrder\Models\GroupOrder;
use IgniterLabs\GroupOrder\Models\Guest;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Start, join and manage a group order (igniterlabs.grouporder), like its Livewire components.
 */
class GroupOrderController
{
    use RedirectsFailures;
    use ResolvesStorefrontPage;

    public function store(Request $request, StartGroupOrder $action): mixed
    {
        $data = $request->validate([
            'split_bill' => ['required', 'integer', 'in:0,1'],
            'spending_limit' => ['nullable', 'integer', 'min:0'],
        ]);

        return $this->attempt(function() use ($action, $data) {
            $manager = GroupOrders::manager();
            throw_if($manager->getGroupOrder(), new ApplicationException(GroupOrders::line('alert_group_order_exists')));
            throw_unless($customer = Auth::customer(), new ApplicationException(GroupOrders::line('alert_login_required')));
            throw_unless($location = Location::current(), new ApplicationException(GroupOrders::line('alert_location_required')));

            $groupOrder = $action->handle($customer, $location, [
                'split_bill' => (int)$data['split_bill'],
                'spending_limit' => ($data['spending_limit'] ?? 0) ?: null,
            ]);
            $manager->setCurrent($groupOrder);
            GroupOrders::rememberHostTable();

            return redirect()->to(StorefrontRoutes::order());
        });
    }

    public function join(string $hash): Response
    {
        $groupOrder = GroupOrder::findByHash($hash);
        $customer = Auth::customer();

        return Inertia::render('GroupOrder/Join', [
            'invite' => GroupOrders::invite($groupOrder),
            'guest' => [
                'first_name' => (string)$customer?->first_name,
                'last_name' => (string)$customer?->last_name,
                'email' => (string)$customer?->email,
            ],
            'meta' => PageMeta::make($groupOrder instanceof GroupOrder ? GroupOrders::line('text_join_title', (string)$groupOrder->customer?->first_name) : GroupOrders::line('text_invalid_link_title')),
        ]);
    }

    public function joinStore(Request $request, string $hash, JoinGroupOrder $action): mixed
    {
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:128'],
            'last_name' => ['required', 'string', 'max:128'],
            'email' => ['required', 'email:filter', 'max:96'],
        ]);

        return $this->attempt(function() use ($hash, $action, $data) {
            throw_unless($groupOrder = GroupOrder::findByHash($hash), new ApplicationException(GroupOrders::line('alert_invalid_invite_link')));
            throw_if($groupOrder->isLocked(), new ApplicationException(GroupOrders::line('alert_group_order_locked')));
            throw_if($groupOrder->isCanceled(), new ApplicationException(GroupOrders::line('alert_group_order_canceled')));

            // Guests order from the host's location.
            $location = $groupOrder->location;
            throw_unless($location instanceof LocationModel, new ApplicationException(GroupOrders::line('alert_location_required')));
            Location::setCurrent($location);

            $guest = $action->handle($groupOrder, $data);

            $manager = GroupOrders::manager();
            $manager->setCurrent($groupOrder);
            $manager->putSession('guestId', $guest->getKey());
            DineInTables::adopt((int)data_get($groupOrder->hostOrder(), 'dining_table_id'));

            return redirect()->route('igniter.theme.order', ['location' => $location->permalink_slug, 'ghash' => $groupOrder->hash])
                ->with('success', GroupOrders::line('alert_group_order_joined', (string)$groupOrder->customer?->first_name));
        });
    }

    public function invite(Request $request, SendInvite $action): mixed
    {
        $data = $request->validate(['email' => ['required', 'email']]);

        return $this->attempt(function() use ($action, $data) {
            $action->handle($this->hostGroupOrder(), $data['email']);

            return back()->with('success', GroupOrders::line('alert_invite_sent'));
        });
    }

    public function lock(LockGroupOrder $action): mixed
    {
        return $this->attempt(function() use ($action) {
            $action->handle($this->hostGroupOrder());

            return back()->with('success', GroupOrders::line('alert_group_order_locked'));
        });
    }

    public function unlock(UnlockGroupOrder $action): mixed
    {
        return $this->attempt(function() use ($action) {
            $action->handle($this->hostGroupOrder());

            return back()->with('success', GroupOrders::line('alert_group_order_unlocked'));
        });
    }

    /**
     * The host cancels the group order; a guest leaves it.
     */
    public function destroy(CancelGroupOrder $cancel, LeaveGroupOrder $leave): mixed
    {
        return $this->attempt(function() use ($cancel, $leave) {
            $manager = GroupOrders::manager();
            $groupOrder = $this->groupOrder();

            if ($manager->isCurrentHost()) {
                $cancel->handle($groupOrder);
                $message = GroupOrders::line('alert_group_order_canceled');
            } else {
                throw_unless($guest = $manager->getGuest(), new ApplicationException(GroupOrders::line('alert_guest_not_found')));
                $leave->handle($groupOrder, $guest);
                $message = GroupOrders::line('alert_left_group_order');
            }

            $manager->clearCurrent();

            return redirect()->to(StorefrontRoutes::order())->with('success', $message);
        });
    }

    public function removeGuest(int $guestId, RemoveGuest $action): mixed
    {
        return $this->attempt(function() use ($guestId, $action) {
            $groupOrder = $this->hostGroupOrder();
            $guest = $groupOrder->guests()->find($guestId);
            if (!$guest instanceof Guest) {
                throw new ApplicationException(GroupOrders::line('alert_guest_not_found'));
            }

            $action->handle($groupOrder, $guest);

            return back()->with('success', GroupOrders::line('alert_guest_removed'));
        });
    }

    protected function groupOrder(): GroupOrder
    {
        $groupOrder = GroupOrders::manager()->getGroupOrder();
        throw_unless($groupOrder, new ApplicationException(GroupOrders::line('alert_group_order_required')));

        return $groupOrder;
    }

    protected function hostGroupOrder(): GroupOrder
    {
        $groupOrder = $this->groupOrder();
        throw_unless(GroupOrders::manager()->isCurrentHost(), new ApplicationException(GroupOrders::line('alert_host_only')));

        return $groupOrder;
    }
}
