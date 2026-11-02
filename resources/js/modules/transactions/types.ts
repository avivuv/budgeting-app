/** List filter as it appears in the query string (TransactionFilterRules). */
export type TransactionFilters = {
    from?: string;
    to?: string;
    type?: App.Enums.TransactionType;
    category_id?: number[];
    wallet_id?: number[];
    user_id?: number[];
    tag?: string[];
    source?: App.Enums.TransactionSource;
    /** Typed amounts like "50rb" are parsed by the server (BR-A03). */
    min?: number | string;
    max?: number | string;
    q?: string;
    sort?: string;
};

export type FilterOptions = {
    wallets: { id: number; name: string; archived: boolean }[];
    categories: Record<
        'expense' | 'income',
        App.DTOs.Category.CategoryItemData[]
    >;
    members: { id: number; name: string }[];
    tags: string[];
    sources: { value: App.Enums.TransactionSource; label: string }[];
};

/** Server-computed totals for the whole filter (INV-06). */
export type Aggregates = {
    count: number;
    income: number;
    expense: number;
    incomeFormatted: string;
    expenseFormatted: string;
    netFormatted: string;
};

export type DailyTotals = Record<
    string,
    {
        income: number;
        expense: number;
        incomeFormatted: string;
        expenseFormatted: string;
    }
>;
