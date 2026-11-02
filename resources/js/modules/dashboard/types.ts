export type Option = { id: number; name: string };

/** Global dashboard filter, mirrored in the query string. */
export type DashboardFilters = {
    period: string | null;
    wallet: number | null;
    member: number | null;
};
