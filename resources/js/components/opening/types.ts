export type PartnerDraft = {
    key: number;
    partner: string;
    reference: string;
    invoice_date: string;
    due_date: string;
    amount?: number;
};

export type PartnerBalance = {
    id: number;
    partner: string;
    reference: string;
    invoice_date: string;
    due_date: string | null;
    amount: number | string;
    documentUrl: string;
};
