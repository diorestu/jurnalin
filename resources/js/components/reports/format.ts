export type AccountBalance = {
    id: number;
    code: string;
    name: string;
    type: string | null;
    balance: string;
    ledgerUrl?: string;
};

const money = new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
});

export const rupiah = (value: string) => money.format(BigInt(value));

export function ledgerBalance(value: string): string {
    const amount = BigInt(value);
    return amount === 0n
        ? rupiah(value)
        : rupiah(String(amount < 0n ? -amount : amount)) +
              (amount < 0n ? ' K' : ' D');
}

const dates = new Intl.DateTimeFormat('id-ID', {
    dateStyle: 'medium',
    timeZone: 'UTC',
});
export const displayDate = (value: string) => dates.format(new Date(value));
