import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import type { CartTotal, SiteShared, StorefrontShared } from './types';

export function useStorefront() {
    const page = usePage();

    return computed(() => page.props.storefront as StorefrontShared);
}

export function useSite() {
    const page = usePage();

    return computed(() => page.props.site as SiteShared);
}

/** Whether dish and category photos are shown (Customize > General). On unless turned off. */
export function useDisplay() {
    const site = useSite();

    return computed(() => ({
        menuImages: site.value.display?.menuImages ?? true,
        categoryImages: site.value.display?.categoryImages ?? true,
    }));
}

export function useFlash() {
    const page = usePage();

    return computed(() => (page.props.flash ?? {}) as { success?: string | null; error?: string | null });
}

export function orderedTotals(totals: CartTotal[]): CartTotal[] {
    const lines = totals.filter((total) => total.code !== 'total');

    return [
        ...lines.filter((total) => total.code === 'subtotal'),
        ...lines.filter((total) => total.code !== 'subtotal'),
    ];
}
