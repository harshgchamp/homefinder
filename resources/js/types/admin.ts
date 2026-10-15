// Why: one definition of the data shape, matching FeatureResource on the server.
// If the resource changes, the type check shows every page that needs updating.
export type Feature = {
    id: number;
    name: string;
    slug: string;
    properties_count?: number; // only sent when the query counted it
    is_new: boolean;
    created_at: string | null;
    created_ago: string | null;
    updated_at: string | null;
};

// Why: every paginated resource collection from Laravel has this same wrapper,
// so it is written once and reused for property types and agencies.
export type Paginated<T> = {
    data: T[];
    meta: {
        current_page: number;
        last_page: number;
        from: number | null;
        to: number | null;
        total: number;
        links: { url: string | null; label: string; active: boolean }[];
    };
};
