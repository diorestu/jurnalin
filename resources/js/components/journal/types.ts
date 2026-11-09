export type JournalLine = {
    account_code: string;
    account_name: string;
    side: 'debit' | 'credit';
    amount?: number;
};

export type JournalTemplate = {
    id: number;
    keyword: string;
    name: string;
    signature: string;
    lines: JournalLine[];
};

export type PostedJournal = {
    id: number;
    date: string;
    description: string | null;
    template_name: string;
    lines: JournalLine[];
};
