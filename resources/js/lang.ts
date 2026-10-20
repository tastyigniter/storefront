import { usePage } from '@inertiajs/vue3';

type Lines = { [key: string]: string | Lines };
type Replace = Record<string, string | number | null | undefined>;

function lines(): Lines {
    return (usePage().props.lang ?? {}) as Lines;
}

function lookup(key: string): string | null {
    const value = key
        .split('.')
        .reduce<string | Lines | undefined>(
            (node, part) => (node && typeof node === 'object' ? node[part] : undefined),
            lines(),
        );

    return typeof value === 'string' ? value : null;
}

// Longest names first, as Laravel does, so `:to` leaves `:total` alone.
function replace(line: string, values: Replace): string {
    return Object.entries(values)
        .sort(([a], [b]) => b.length - a.length)
        .reduce((text, [name, value]) => text.replaceAll(`:${name}`, String(value ?? '')), line);
}

/** A storefront string from the active theme's language namespace. */
export function t(key: string, values: Replace = {}): string {
    return replace(lookup(key) ?? key, values);
}

/** Pick the singular or plural form of "one|many" by count; `:count` is replaced too. */
export function tc(key: string, count: number, values: Replace = {}): string {
    const [one, many = one] = (lookup(key) ?? key).split('|');

    return replace(count === 1 ? one : many, { count, ...values });
}
