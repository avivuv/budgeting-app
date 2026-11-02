type Status = App.Enums.ReceiptStatus;

/** Badge colours per receipt status (labels come from the server). */
export const statusTone: Record<Status, string> = {
    pending: 'bg-muted text-muted-foreground',
    processing: 'bg-primary-soft text-primary',
    parsed: 'bg-warning/15 text-warning',
    confirmed: 'bg-income/15 text-income',
    failed: 'bg-destructive/10 text-destructive',
    rejected: 'bg-muted text-muted-foreground',
    expired: 'bg-muted text-muted-foreground',
};

/** Still being read: the page polls until this is false. */
export function isReading(status: Status): boolean {
    return status === 'pending' || status === 'processing';
}

/** Review warnings set by the server (BR-H03, BR-H04, BR-H06). */
export const flagMessages: Record<string, string> = {
    total_mismatch:
        'Total tidak cocok dengan jumlah item (selisih lebih dari 2%). Periksa lagi totalnya.',
    date_replaced:
        'Tanggal struk tidak terbaca atau tidak wajar, jadi diganti hari ini.',
    possible_duplicate:
        'Kemungkinan duplikat: ada struk lain dengan toko, tanggal, dan total yang sama.',
};
