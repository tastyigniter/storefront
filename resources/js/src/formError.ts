/** A general validation message arrives under `form`, which is not a submitted field. */
export function formError(errors: object): string | undefined {
    const value = (errors as Record<string, unknown>).form;

    return typeof value === 'string' ? value : undefined;
}
