import { ArrowDownLeft, ArrowLeftRight, ArrowUpRight } from '@lucide/vue';

/** Icon, colour token and sign per transaction type (docs/14 §1.2). */
export const typeStyle = {
    expense: {
        icon: ArrowUpRight,
        color: 'text-expense',
        bg: 'bg-expense/10',
        sign: '−',
    },
    income: {
        icon: ArrowDownLeft,
        color: 'text-income',
        bg: 'bg-income/10',
        sign: '+',
    },
    transfer: {
        icon: ArrowLeftRight,
        color: 'text-transfer',
        bg: 'bg-transfer/10',
        sign: '',
    },
} as const;

/** Channel a transaction came from (BR-B07). */
export const sourceLabel: Record<App.Enums.TransactionSource, string> = {
    web: 'Web',
    api: 'API',
    telegram: 'Telegram',
    receipt: 'Struk',
    recurring: 'Berulang',
};
