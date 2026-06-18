export interface EventImage {
    url: string;
}

export interface EventRow {
    id: string;
    type: string;
    status: string;
    created_time: number | null;
    latitude: number | null;
    longitude: number | null;
    payload: Record<string, unknown>;
    location_label: string | null;
    timezone: string;
    images: string[];
    user: { id: number; name: string } | null;
}

export interface EventFilters {
    status: string;
    date_from: string;
    date_to: string;
    location: string;
}
