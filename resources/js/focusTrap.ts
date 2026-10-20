import { nextTick, onBeforeUnmount, watch, type Ref } from 'vue';

const FOCUSABLE = [
    'a[href]',
    'button:not([disabled])',
    'input:not([disabled]):not([type="hidden"])',
    'select:not([disabled])',
    'textarea:not([disabled])',
    '[tabindex]:not([tabindex="-1"])',
].join(', ');

const held = new WeakMap<HTMLElement, number>();

function shown(element: HTMLElement): boolean {
    if (element.closest('[hidden]')) {
        return false;
    }

    const style = getComputedStyle(element);

    return style.display !== 'none' && style.visibility !== 'hidden';
}

function focusable(root: HTMLElement): HTMLElement[] {
    return [...root.querySelectorAll<HTMLElement>(FOCUSABLE)].filter(
        (element) => element.tabIndex !== -1 && shown(element),
    );
}

/** Hide the rest of the page from keyboard and assistive tech while this dialog is open. */
function holdOutside(dialog: HTMLElement): HTMLElement[] {
    const changed: HTMLElement[] = [];
    let node: HTMLElement | null = dialog;

    while (node && node !== document.body) {
        const parent: HTMLElement | null = node.parentElement;
        if (!parent) {
            break;
        }

        for (const sibling of parent.children) {
            if (!(sibling instanceof HTMLElement) || sibling === node) {
                continue;
            }

            const count = held.get(sibling) ?? 0;
            if (count === 0) {
                sibling.inert = true;
            }
            held.set(sibling, count + 1);
            changed.push(sibling);
        }

        node = parent;
    }

    return changed;
}

function release(elements: HTMLElement[]): void {
    for (const element of elements) {
        const count = (held.get(element) ?? 1) - 1;
        if (count <= 0) {
            element.inert = false;
            held.delete(element);
        } else {
            held.set(element, count);
        }
    }
}

/**
 * Move focus into an open dialog, keep Tab inside it, and restore focus on close.
 * `dialog` is the overlay (or the dialog itself when it is inline).
 */
export function useFocusTrap(
    open: Ref<boolean>,
    dialog: Ref<HTMLElement | null>,
    fallback: Ref<HTMLElement | null> = { value: null } as Ref<HTMLElement | null>,
): void {
    let restore: HTMLElement | null = null;
    let heldElements: HTMLElement[] = [];
    let active = false;

    function onKeydown(event: KeyboardEvent): void {
        const node = dialog.value;
        if (event.key !== 'Tab' || !node) {
            return;
        }

        const target = event.target instanceof Element ? event.target : null;
        const owner = target?.closest('[role="dialog"], [role="alertdialog"]');
        if (owner && !node.contains(owner)) {
            return;
        }

        const items = focusable(node);
        if (items.length === 0) {
            return;
        }

        const first = items[0];
        const last = items[items.length - 1];
        const active = document.activeElement;
        if (event.shiftKey && (active === first || !node.contains(active))) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && (active === last || !node.contains(active))) {
            event.preventDefault();
            first.focus();
        }
    }

    function cleanup(): void {
        document.removeEventListener('keydown', onKeydown);
        release(heldElements);
        heldElements = [];
    }

    watch(
        open,
        async (isOpen) => {
            if (!isOpen) {
                if (!active) {
                    return;
                }

                active = false;
                cleanup();
                await nextTick();
                if (restore?.isConnected) {
                    restore.focus();
                } else {
                    fallback.value?.focus();
                }
                restore = null;

                return;
            }

            cleanup();
            active = true;
            restore = document.activeElement instanceof HTMLElement ? document.activeElement : null;
            await nextTick();
            const node = dialog.value;
            if (!node) {
                return;
            }

            heldElements = holdOutside(node);
            document.addEventListener('keydown', onKeydown);
            focusable(node)[0]?.focus();
        },
        { flush: 'post', immediate: true },
    );

    onBeforeUnmount(cleanup);
}
