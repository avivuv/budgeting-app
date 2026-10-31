declare namespace App {
    namespace DTOs {
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
        namespace Transaction {
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
        export type CategoryType = 'income' | 'expense';
        export type FamilyRole = 'owner' | 'admin' | 'member' | 'viewer';
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
