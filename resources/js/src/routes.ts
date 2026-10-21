const templates: Record<string, string> = {};

/** Replace the shared route templates. Called when a page's props arrive. */
export function syncRoutes(routes: Record<string, string> | undefined): void {
    for (const key of Object.keys(templates)) {
        delete templates[key];
    }

    Object.assign(templates, routes ?? {});
}

/**
 * Build a storefront URL from a named route.
 * Parameters that appear in the path fill `{placeholders}`; the rest become a query string.
 */
export function route(name: string, params: Record<string, string | number | null | undefined> = {}): string {
    const template = templates[name];
    if (!template) {
        throw new Error(`Unknown route [${name}].`);
    }

    const used = new Set<string>();
    const path = template.replace(/\{([^}?]+)\??}/g, (_, key: string) => {
        used.add(key);
        const value = params[key];
        if (value === undefined || value === null || value === '') {
            throw new Error(`Missing parameter [${key}] for route [${name}].`);
        }

        return encodeURIComponent(String(value));
    });

    const query = new URLSearchParams();
    for (const [key, value] of Object.entries(params)) {
        if (used.has(key) || value === undefined || value === null) {
            continue;
        }

        query.append(key, String(value));
    }

    const search = query.toString();

    return search === '' ? path : `${path}?${search}`;
}

/** The menu for a location, or the locations list when none is selected. */
export function orderUrl(slug?: string | null): string {
    return slug ? route('igniter.theme.order', { location: slug }) : route('igniter.theme.locations');
}
