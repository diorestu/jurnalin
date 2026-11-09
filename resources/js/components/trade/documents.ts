export type Direction = 'penjualan' | 'pembelian';
export type Money = number | string;

export type Summary = {
    id: number;
    number: string;
    kind: string;
    kindLabel: string;
    date: string;
    partner: string;
    total: Money;
    openAmount: Money | null;
    url: string;
};

export type Kind = { value: string; label: string };
export type Account = { id: number; code: string; name: string };

export type Detail = Summary & {
    due_date: string | null;
    reference: string | null;
    notes: string | null;
    notesRuns: {
        insert: string;
        attributes?: { bold?: boolean; italic?: boolean; underline?: boolean };
    }[];
    appliedAdvance: Money;
    availableAmount: Money | null;
    source: { number: string; url: string } | null;
    lines: {
        id: number;
        name: string;
        quantity: Money;
        unit_price: Money;
        total: Money;
    }[];
    journalId: number | null;
    journalUrl: string | null;
};

export type Source = {
    id: number;
    number: string;
    partner: string;
    date: string;
    lines: {
        id: number;
        name: string;
        quantity: Money;
        unit_price: Money;
        remaining: Money;
    }[];
    availableAmount: Money | null;
};

const currency = new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
});
const dates = new Intl.DateTimeFormat('id-ID', {
    dateStyle: 'medium',
    timeZone: 'UTC',
});

export function rupiah(amount: Money | bigint): string {
    return currency.format(BigInt(amount));
}

export function formatDate(value: string): string {
    return dates.format(new Date(value));
}

export function partnerLabel(direction: Direction): string {
    return direction === 'penjualan' ? 'Pelanggan' : 'Pemasok';
}

export function openAmountLabel(direction: Direction, amount: Money): string {
    if (BigInt(amount) < 0n) {
        return 'Kredit retur';
    }

    return direction === 'penjualan' ? 'Sisa piutang' : 'Sisa utang';
}
