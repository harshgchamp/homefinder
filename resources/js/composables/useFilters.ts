import { router } from '@inertiajs/vue3';
import { watchDebounced } from '@vueuse/core';
import { computed, reactive } from 'vue';

type FilterValue = string | number | null | number[] | string[];

type UseFiltersOptions<T> = {
    /** Values from the server, so a reload or shared link shows the same filters. */
    initial: T;
    /** What "Reset" goes back to. Also: values equal to these are left out of the URL. */
    defaults: T;
    /** The page URL to reload with the new query string. */
    url: () => string;
    /**
     * Partial reload: only these props are fetched again. The server skips the
     * rest (e.g. the select box options), so typing in search stays fast.
     */
    only?: string[];
    debounce?: number;
};

/**
 * A reusable composable for list pages with filters in the URL.
 *
 * Why a composable: the logic (debounce, clean the query, partial reload,
 * reset, count active filters) is the same for every list page. Each page
 * calls useFilters() and only writes its own template.
 *
 * Usage:
 *   const { filters, reset, activeCount, query } = useFilters({ initial, defaults, url });
 *   <input v-model="filters.search">   // changes reload the list automatically
 */
export function useFilters<T extends Record<string, FilterValue>>(
    options: UseFiltersOptions<T>,
) {
    // Why a copy: editing the filters must not change the server's props object.
    const filters = reactive({ ...options.initial }) as T;

    // Why: ?search=&city=&features= clutters the URL. Drop empty values and
    // values equal to the default (sort=newest) so the URL only shows real choices.
    const query = computed(() => {
        const result: Record<string, FilterValue> = {};

        for (const [key, value] of Object.entries(filters)) {
            const isEmpty =
                value === null ||
                value === '' ||
                (Array.isArray(value) && value.length === 0);

            if (!isEmpty && value !== options.defaults[key]) {
                result[key] = value;
            }
        }

        return result;
    });

    // Why: the badge on the "Reset" button ("Reset (3)"). sort does not count.
    const activeCount = computed(
        () => Object.keys(query.value).filter((key) => key !== 'sort').length,
    );

    const apply = (): void => {
        router.get(options.url(), query.value, {
            preserveState: true, // keep typed text and focus in the inputs
            preserveScroll: true,
            replace: true, // one history entry for the search, not one per key press
            only: options.only,
        });
    };

    // Why deep: features is an array, and ticking a box changes it in place.
    // Why debounce: wait until typing stops, so one request is sent, not one per key.
    watchDebounced(() => ({ ...filters }), apply, {
        debounce: options.debounce ?? 300,
        deep: true,
    });

    const reset = (): void => {
        Object.assign(filters, options.defaults);
    };

    return { filters, query, activeCount, reset };
}
