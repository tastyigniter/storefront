import { usePoll } from '@inertiajs/vue3';
import { computed, toValue, watch, type MaybeRefOrGetter } from 'vue';
import type { OrderDetail } from './types';

/** How often the success / order page refetches order status while it may still change. */
export const ORDER_PROGRESS_POLL_MS = 15_000;

export function orderProgressFinished(order: OrderDetail): boolean {
    return order.isCanceled || order.progress === null || order.progress === 3;
}

/**
 * Partially reload `order` on the current Inertia page until the progress bar is done.
 */
export function useOrderProgressPoll(order: MaybeRefOrGetter<OrderDetail>): void {
    const finished = computed(() => orderProgressFinished(toValue(order)));

    const { stop, start } = usePoll(
        ORDER_PROGRESS_POLL_MS,
        { only: ['order'] },
        { autoStart: false, background: 'pause' },
    );

    watch(finished, (done) => (done ? stop() : start()), { immediate: true });
}
