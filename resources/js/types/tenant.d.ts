export interface CompanyScope {
    id: number;
    ruc: string;
    name: string;
}

export interface Company {
    id: number;
    ruc: string;
    name: string;
    matrix_address: string;
    contributor_type_id: number | null;
    special_contribution: number | null;
    accounting: boolean;
    retention_agent: number | null;
    phantom_taxpayer: boolean;
    no_transactions: boolean;
    phone: string | null;
    email: string | null;
    type_declaration: string | null;
    pass_sri: string | null;
    [key: string]: any;
}

interface ContributorType {
    id: number;
    description: string;
}

export interface Account {
    id: number;
    code: string;
    name: string;
}

export interface RetentionOption {
    id: number;
    code: string;
    type: string;
    description: string;
    percentage: number;
}

export interface RetentionItem {
    id?: number;
    retention_id: number | null;
    retention?: { code: string; description: string; type: string };
    base: number | string;
    percentage: number | string;
    value: number | string;
}

export interface Shop {
    id: number;
    acount_id: number | null;
    contact_id: number;
    contact: { id: number; identification: string; name: string } | null;
    voucher_type_id: number;
    serie: string;
    emision: string;
    autorization: string;
    autorized_at: string | null;
    initial: string;
    sub_total: string;
    no_iva: string;
    base0: string;
    base5: string;
    base8: string;
    base12: string;
    base15: string;
    iva5: string;
    iva8: string;
    iva12: string;
    iva15: string;
    aditional_discount: string;
    discount: string;
    ice: string;
    total: string;
    state: string;
    serie_retention: string | null;
    date_retention: string | null;
    state_retention: string | null;
    autorization_retention: string | null;
    retention_at: string | null;
    account: Account | null;
    retention_items: RetentionItem[];
}

export interface VoucherType {
    id: number;
    code: string;
    description: string;
}
