export type FamilyOption = {
    id: number;
    name: string;
    role: App.Enums.FamilyRole;
};

export type CurrentFamily = {
    id: number;
    name: string;
    role: App.Enums.FamilyRole;
    role_label: string;
    period_start_day: number;
    can: {
        write: boolean;
        manage: boolean;
    };
};

export type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    prev_page_url: string | null;
    next_page_url: string | null;
};
