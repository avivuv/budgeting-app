type Money = App.DTOs.Report.MoneyData;

/** ReportService::build() — the same payload as the API and the exports. */
export type Report = {
    tab: App.Enums.ReportTab;
    title: string;
    from: string;
    to: string;
    rangeLabel: string;
    year: number;
    type: 'income' | 'expense';
    totals: { income: Money; expense: Money; net: Money };
    rows: unknown;
    budgetTotals: {
        amountFormatted: string;
        spentFormatted: string;
        remainingFormatted: string;
        percentage: number | null;
    } | null;
};

export type Preset = { label: string; from: string; to: string };
