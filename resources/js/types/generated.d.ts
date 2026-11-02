declare namespace App {
    namespace DTOs {
        namespace Budget {
            export type BudgetItemData = {
                id: number;
                categoryId: number;
                categoryName: string;
                categoryIcon: string | null;
                categoryColor: string | null;
                isSubCategory: boolean;
                amount: number;
                amountFormatted: string;
                amountShort: string;
                spent: number;
                spentFormatted: string;
                spentShort: string;
                remaining: number;
                remainingFormatted: string;
                percentage: number | null;
                status: App.Enums.BudgetStatus;
                statusLabel: string;
                dailyAllowance: number;
                dailyAllowanceFormatted: string;
            };
        }
        namespace Category {
            export type CategoryItemData = {
                id: number;
                type: App.Enums.CategoryType;
                name: string;
                parentId: number | null;
                icon: string | null;
                color: string | null;
                keywords: string[];
                isDefault: boolean;
                isArchived: boolean;
                children: App.DTOs.Category.CategoryItemData[];
            };
        }
        namespace Dashboard {
            export type CashflowPointData = {
                key: string;
                label: string;
                income: number;
                expense: number;
                net: number;
            };
            export type CategorySliceData = {
                categoryId: number;
                name: string;
                color: string | null;
                icon: string | null;
                amount: number;
                formatted: string;
                percentage: number | null;
            };
            export type DashboardSummaryData = {
                balance: App.DTOs.Dashboard.StatData;
                income: App.DTOs.Dashboard.StatData;
                expense: App.DTOs.Dashboard.StatData;
                net: App.DTOs.Dashboard.StatData;
            };
            export type MemberTotalData = {
                userId: number | null;
                name: string;
                amount: number;
                formatted: string;
                count: number;
                percentage: number | null;
            };
            export type PeriodData = {
                key: string;
                label: string;
                range: string;
                start: string;
                end: string;
                previousKey: string;
                nextKey: string | null;
                isCurrent: boolean;
            };
            export type StatData = {
                amount: number;
                formatted: string;
                previous: number;
                change: number | null;
            };
            export type TrendPointData = {
                date: string;
                label: string;
                cumulative: number | null;
                pace: number;
            };
        }
        namespace Report {
            export type CashflowRowData = {
                key: string;
                label: string;
                start: string;
                end: string;
                income: App.DTOs.Report.MoneyData;
                expense: App.DTOs.Report.MoneyData;
                net: App.DTOs.Report.MoneyData;
                opening: App.DTOs.Report.MoneyData;
                closing: App.DTOs.Report.MoneyData;
            };
            export type CategoryRowData = {
                id: number;
                name: string;
                color: string | null;
                amount: App.DTOs.Report.MoneyData;
                share: number | null;
                previous: App.DTOs.Report.MoneyData;
                change: number | null;
                count: number;
                children: App.DTOs.Report.CategoryRowData[];
            };
            export type MemberRowData = {
                userId: number | null;
                name: string;
                income: App.DTOs.Report.MoneyData;
                expense: App.DTOs.Report.MoneyData;
                expenseShare: number | null;
                count: number;
            };
            export type MoneyData = {
                amount: number;
                formatted: string;
            };
            export type RankedRowData = {
                name: string;
                amount: App.DTOs.Report.MoneyData;
                count: number;
                share: number | null;
            };
            export type WalletMovementData = {
                id: number;
                name: string;
                typeLabel: string;
                isArchived: boolean;
                opening: App.DTOs.Report.MoneyData;
                income: App.DTOs.Report.MoneyData;
                expense: App.DTOs.Report.MoneyData;
                transferIn: App.DTOs.Report.MoneyData;
                transferOut: App.DTOs.Report.MoneyData;
                closing: App.DTOs.Report.MoneyData;
            };
            export type YearlyReportData = {
                year: number;
                months: string[];
                categories: {
                    id: number;
                    name: string;
                    values: number[];
                    total: App.DTOs.Report.MoneyData;
                }[];
                monthTotals: App.DTOs.Report.MoneyData[];
                total: App.DTOs.Report.MoneyData;
                income: App.DTOs.Report.MoneyData;
                peakMonth: string | null;
            };
        }
        namespace Transaction {
            export type ParsedTransactionData = {
                line: string;
                type: App.Enums.TransactionType;
                typeLabel: string;
                amount: number | null;
                amountFormatted: string | null;
                date: string;
                dateFormatted: string;
                walletId: number | null;
                walletName: string | null;
                toWalletId: number | null;
                toWalletName: string | null;
                categoryId: number | null;
                categoryName: string | null;
                categorySource: string | null;
                note: string | null;
                tags: string[];
                missing: string[];
                complete: boolean;
            };
            export type TransactionItemData = {
                id: number;
                type: App.Enums.TransactionType;
                typeLabel: string;
                amount: number;
                amountFormatted: string;
                date: string;
                dateFormatted: string;
                note: string | null;
                source: App.Enums.TransactionSource;
                walletId: number;
                walletName: string;
                toWalletId: number | null;
                toWalletName: string | null;
                categoryId: number | null;
                categoryName: string | null;
                categoryIcon: string | null;
                categoryColor: string | null;
                creatorName: string | null;
                tags: string[];
                canEdit: boolean;
            };
        }
        namespace Wallet {
            export type WalletItemData = {
                id: number;
                name: string;
                type: App.Enums.WalletType;
                typeLabel: string;
                initialBalance: number;
                balance: number;
                balanceFormatted: string;
                color: string | null;
                icon: string | null;
                isArchived: boolean;
            };
        }
    }
    namespace Enums {
        export type Appearance = 'system' | 'light' | 'dark';
        export type BudgetStatus = 'safe' | 'warning' | 'over';
        export type CategoryType = 'income' | 'expense';
        export type FamilyRole = 'owner' | 'admin' | 'member' | 'viewer';
        export type ReportTab =
            | 'cashflow'
            | 'categories'
            | 'members'
            | 'wallets'
            | 'budget'
            | 'yearly'
            | 'merchants'
            | 'tags';
        export type TransactionSource =
            | 'web'
            | 'api'
            | 'telegram'
            | 'receipt'
            | 'recurring';
        export type TransactionType = 'income' | 'expense' | 'transfer';
        export type WalletType =
            | 'cash'
            | 'bank'
            | 'ewallet'
            | 'credit_card'
            | 'other';
    }
}
