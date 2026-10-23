import { router } from '@inertiajs/vue3';

const progressOptions = {
    delay: 150,
    showSpinner: false,
    popover: false,
} as const;

/** Shared Inertia progress settings for Glass and Plum. */
export function storefrontProgress(color: string) {
    return {
        ...progressOptions,
        color,
    };
}

/** Scroll to the flash banner after a redirect that surfaced an error. */
export function installStorefrontInertia(): void {
    router.on('success', (event) => {
        const flash = event.detail.page.flash as { error?: string | null } | undefined;
        if (flash?.error) {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
    });
}
