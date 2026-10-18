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

// --- Properties -------------------------------------------------------------

// Why: a select box option. Ids are numbers (agencies), enum values are strings.
export type Option<T = string | number> = { value: T; label: string };

// Why: enums arrive from PropertyResource as { value, label }.
export type EnumValue = { value: string; label: string };

export type PropertyImage = {
    id: number;
    url: string;
    sort_order: number;
};

// Why: optional fields (?) are only sent by some pages, matching the
// whenHas / whenLoaded / whenCounted calls in PropertyResource.
export type Property = {
    id: number;
    title: string;
    slug: string;
    agency_id: number;
    property_type_id: number;
    description?: string;
    listing_type: EnumValue;
    status: EnumValue;
    publication: EnumValue;
    price: number;
    formatted_price: string;
    bedrooms: number;
    bathrooms: number;
    furnished?: boolean | null;
    address_line_1?: string;
    city: string;
    postcode: string;
    published_at: string | null;
    published_at_input: string | null;
    last_enquiry_ago?: string | null;
    created_at: string | null;
    updated_ago: string | null;
    deleted_at: string | null;
    is_trashed: boolean;
    agency?: { id: number; name: string };
    property_type?: { id: number; name: string };
    features?: { id: number; name: string }[];
    images?: PropertyImage[];
    main_image_url?: string | null;
    // From PropertyPolicy, for the logged-in user (absent for guests).
    can?: {
        update: boolean;
        delete: boolean;
        restore: boolean;
        forceDelete: boolean;
    };
    enquiries_count?: number;
    favourites_count?: number;
};

// Why: mirrors PropertyIndexRequest::filters() on the server.
export type PropertyFilters = {
    search: string | null;
    listing_type: string | null;
    status: string | null;
    publication: string | null;
    agency_id: number | null;
    property_type_id: number | null;
    city: string | null;
    min_price: number | null;
    max_price: number | null;
    min_bedrooms: number | null;
    features: number[];
    trashed: string | null;
    sort: string;
};

// Why: mirrors PropertyController::formOptions().
export type PropertyFormOptions = {
    agencies: Option<number>[];
    types: Option<number>[];
    features: Option<number>[];
    listingTypes: Option<string>[];
    statuses: Option<string>[];
    statusesByListingType: Record<string, string[]>;
};

// Why: mirrors PropertyController::filterOptions().
export type PropertyFilterOptions = PropertyFormOptions & {
    publications: Option<string>[];
    cities: string[];
};
