<?php

declare(strict_types=1);

/*
 * Stand-ins for the igniterlabs.grouporder extension, which is not installed in the test app.
 * They cover only what Glass calls; tests set their state directly.
 */

namespace IgniterLabs\GroupOrder\Classes {
    use IgniterLabs\GroupOrder\Models\GroupOrder;
    use IgniterLabs\GroupOrder\Models\Guest;

    if (!class_exists(Manager::class)) {
        class Manager
        {
            public ?GroupOrder $groupOrder = null;

            public bool $host = false;

            public ?Guest $guest = null;

            public array $session = [];

            public function getGroupOrder(): ?GroupOrder
            {
                return $this->groupOrder;
            }

            public function isCurrentHost(): bool
            {
                return $this->groupOrder !== null && $this->host;
            }

            public function isCurrentGuest(): bool
            {
                return $this->groupOrder !== null && !$this->host;
            }

            public function getGuest(): ?Guest
            {
                return $this->guest;
            }

            public function setCurrent(GroupOrder $groupOrder): self
            {
                $this->groupOrder = $groupOrder;

                return $this;
            }

            public function putSession(string $key, mixed $value): void
            {
                $this->session[$key] = $value;
            }

            public function clearCurrent(): void
            {
                $this->groupOrder = null;
            }
        }
    }
}

namespace IgniterLabs\GroupOrder\Models {
    use Illuminate\Support\Collection;

    if (!class_exists(GroupOrder::class)) {
        class GroupOrder
        {
            /** @var array<string, self> */
            public static array $records = [];

            public int $id = 1;

            public string $hash = 'abc123';

            public mixed $location = null;

            public mixed $customer = null;

            public string $payment = 'guest';

            public ?float $spending_limit = null;

            public bool $locked = false;

            public bool $canceled = false;

            public bool $completed = false;

            /** null, 'remaining' or 'passed' */
            public ?string $deadline = null;

            public ?string $deliveryAddress = null;

            public mixed $host = null;

            public bool $anySubmitted = false;

            public Collection $guests;

            public function __construct(array $attributes = [])
            {
                $this->guests = collect();
                foreach ($attributes as $key => $value) {
                    $this->{$key} = $value;
                }
            }

            public static function findByHash(string $hash): ?self
            {
                return self::$records[$hash] ?? null;
            }

            public function getKey(): int
            {
                return $this->id;
            }

            public function guests(): object
            {
                return new class($this->guests)
                {
                    public function __construct(protected Collection $guests) {}

                    public function find(int $id): ?Guest
                    {
                        return $this->guests->first(fn(Guest $guest): bool => $guest->id === $id);
                    }
                };
            }

            public function hostOrder(): mixed
            {
                return $this->host;
            }

            public function isLocked(): bool
            {
                return $this->locked;
            }

            public function isCanceled(): bool
            {
                return $this->canceled;
            }

            public function isCompleted(): bool
            {
                return $this->completed;
            }

            public function hasDeadline(): bool
            {
                return $this->deadline !== null;
            }

            public function isPastDeadline(): bool
            {
                return $this->deadline === 'passed';
            }

            public function getTimeRemaining(): ?string
            {
                return $this->deadline === 'remaining' ? '25 minutes' : null;
            }

            public function hostPays(): bool
            {
                return $this->payment === 'host';
            }

            public function guestHasSpendingLimit(): bool
            {
                return $this->payment === 'limit';
            }

            public function formattedDeliveryAddress(): ?string
            {
                return $this->deliveryAddress;
            }

            public function anyGuestsSubmitted(): bool
            {
                return $this->anySubmitted;
            }
        }
    }

    if (!class_exists(Guest::class)) {
        class Guest
        {
            public function __construct(
                public int $id = 1,
                public string $name = 'Ada Guest',
                public string $status = 'joined',
                public mixed $order = null,
            ) {}

            public function getKey(): int
            {
                return $this->id;
            }

            public function hasSubmittedOrder(): bool
            {
                return $this->status === 'submitted';
            }

            public function hasLeft(): bool
            {
                return $this->status === 'left';
            }
        }
    }
}

namespace IgniterLabs\GroupOrder\Actions {
    use Igniter\Flame\Exception\ApplicationException;
    use IgniterLabs\GroupOrder\Models\GroupOrder;
    use IgniterLabs\GroupOrder\Models\Guest;

    if (!class_exists(FakeAction::class)) {
        /**
         * Records each call; set $throw to make the next call fail.
         */
        abstract class FakeAction
        {
            /** @var list<array{0: string, 1: array}> */
            public static array $calls = [];

            public static ?string $throw = null;

            public static mixed $result = null;

            protected function record(array $arguments): mixed
            {
                self::$calls[] = [static::class, $arguments];
                if (self::$throw !== null) {
                    throw new ApplicationException(self::$throw);
                }

                return self::$result;
            }
        }

        class StartGroupOrder extends FakeAction
        {
            public function handle(mixed $customer, mixed $location, array $data): GroupOrder
            {
                return $this->record(func_get_args()) ?? new GroupOrder;
            }
        }

        class JoinGroupOrder extends FakeAction
        {
            public function handle(GroupOrder $groupOrder, array $attributes): Guest
            {
                return $this->record(func_get_args()) ?? new Guest(7, $attributes['first_name']);
            }
        }

        class SendInvite extends FakeAction
        {
            public function handle(GroupOrder $groupOrder, string $email): void
            {
                $this->record(func_get_args());
            }
        }

        class LockGroupOrder extends FakeAction
        {
            public function handle(GroupOrder $groupOrder): void
            {
                $this->record(func_get_args());
            }
        }

        class UnlockGroupOrder extends FakeAction
        {
            public function handle(GroupOrder $groupOrder): void
            {
                $this->record(func_get_args());
            }
        }

        class CancelGroupOrder extends FakeAction
        {
            public function handle(GroupOrder $groupOrder): void
            {
                $this->record(func_get_args());
            }
        }

        class LeaveGroupOrder extends FakeAction
        {
            public function handle(GroupOrder $groupOrder, Guest $guest): void
            {
                $this->record(func_get_args());
            }
        }

        class RemoveGuest extends FakeAction
        {
            public function handle(GroupOrder $groupOrder, Guest $guest): void
            {
                $this->record(func_get_args());
            }
        }
    }
}
