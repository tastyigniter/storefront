import { Store, useStore } from '@tanstack/vue-store';
import { route } from './routes';
import type { CartLine, MenuItem } from './types';

export const uiStore = new Store({
    cartOpen: false,
    cartBump: 0,
    fulfilmentOpen: false,
    groupOrder: null as null | 'start' | 'details' | 'invite',
    itemModal: null as null | { item: MenuItem | null; line: CartLine | null },
});

export function useUi() {
    return useStore(uiStore);
}

export function openCart(): void {
    uiStore.setState((state) => ({ ...state, cartOpen: true }));
}

/** Wiggle the basket button so the customer sees the item went in. */
export function bumpCart(): void {
    uiStore.setState((state) => ({ ...state, cartBump: state.cartBump + 1 }));
}

export function closeCart(): void {
    uiStore.setState((state) => ({ ...state, cartOpen: false }));
}

export function openFulfilment(): void {
    uiStore.setState((state) => ({ ...state, fulfilmentOpen: true }));
}

/** Open the group order window on one of its views. */
export function openGroupOrder(view: 'start' | 'details' | 'invite'): void {
    uiStore.setState((state) => ({ ...state, groupOrder: view }));
}

export function closeGroupOrder(): void {
    uiStore.setState((state) => ({ ...state, groupOrder: null }));
}

export function closeFulfilment(): void {
    uiStore.setState((state) => ({ ...state, fulfilmentOpen: false }));
}

let scrollLocks = 0;

/** Keep the page still while a modal is open. Nested modals share one lock. */
export function lockScroll(): void {
    if (typeof document === 'undefined') {
        return;
    }

    scrollLocks += 1;
    document.documentElement.style.overflow = 'hidden';
}

export function unlockScroll(): void {
    if (typeof document === 'undefined') {
        return;
    }

    scrollLocks = Math.max(0, scrollLocks - 1);
    if (scrollLocks === 0) {
        document.documentElement.style.overflow = '';
    }
}

/** Open the dish modal to add a new item. */
export function showItem(item: MenuItem): void {
    uiStore.setState((state) => ({ ...state, itemModal: { item, line: null } }));
}

/** Open the dish modal to edit a basket line; the dish is fetched when not already loaded. */
export async function editLine(line: CartLine): Promise<void> {
    uiStore.setState((state) => ({ ...state, cartOpen: false, itemModal: { item: null, line } }));

    const response = await fetch(route('igniter.theme.cart.item', { menuId: line.menuId }), {
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    });
    const item = response.ok ? ((await response.json()) as MenuItem) : null;

    uiStore.setState((state) =>
        state.itemModal?.line?.rowId === line.rowId ? { ...state, itemModal: item ? { item, line } : null } : state,
    );
}

export function closeItem(): void {
    uiStore.setState((state) => ({ ...state, itemModal: null }));
}

export const accents = ['#F4A261', '#E9C46A', '#7DCFB6', '#E07A5F', '#8ECAE6', '#CDB4DB', '#F2CC8F'];

export function accentFor(id: number): string {
    return accents[Math.abs(id) % accents.length];
}
