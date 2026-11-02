export type AvailableCategory = {
    id: number;
    name: string;
    parentName: string | null;
};

/** Server totals over the period's budgets (sub-categories not double counted). */
export type BudgetTotals = {
    amount: number;
    spent: number;
    remaining: number;
    percentage: number | null;
    status: App.Enums.BudgetStatus;
    amountFormatted: string;
    spentFormatted: string;
    remainingFormatted: string;
    dailyAllowanceFormatted: string;
};
