import Dexie, { type Table } from 'dexie';

export interface OfflineProduct {
    id: number;
    code: string;
    barcode: string | null;
    name: string;
    generic_name: string | null;
    form: string | null;
    dosage: string | null;
    laboratory: string | null;
    category_id: number | null;
    base_unit: string | null;
    sale_unit: string | null;
    conversion_factor: number;
    purchase_price: number;
    sale_price: number;
    alert_threshold: number;
    version: number;
}

export interface OfflineStockLevel {
    pharmacy_id: number;
    product_id: number;
    quantity: number;
    version: number;
}

export interface OfflineCustomer {
    id: number;
    name: string;
    phone: string | null;
    email: string | null;
    insurance_company_id: number | null;
    version: number;
}

export interface OfflineInsurer {
    id: number;
    name: string;
    code: string | null;
    version: number;
}

export interface OfflineInsuranceRule {
    id: number;
    insurance_company_id: number;
    product_id: number | null;
    category_id: number | null;
    type: string;
    value: number;
    version: number;
}

export interface OutboxEvent {
    client_event_id: string;
    type: string;
    pharmacy_id: number | null;
    payload: Record<string, unknown>;
    occurred_at: string;
    schema_version: number;
    status: 'pending' | 'sent' | 'failed' | 'conflict';
    attempts: number;
    created_at: string;
}

export interface MetaValue {
    key: string;
    value: unknown;
}

export class PharmaGestDb extends Dexie {
    products!: Table<OfflineProduct, number>;
    stock_levels!: Table<OfflineStockLevel, [number, number]>;
    customers!: Table<OfflineCustomer, number>;
    insurers!: Table<OfflineInsurer, number>;
    insurance_rules!: Table<OfflineInsuranceRule, number>;
    outbox!: Table<OutboxEvent, string>;
    meta!: Table<MetaValue, string>;

    constructor() {
        super('pharmagest');
        this.version(1).stores({
            products: 'id, code, barcode, name',
            stock_levels: '[pharmacy_id+product_id], pharmacy_id, product_id',
            customers: 'id, name',
            insurers: 'id, name',
            insurance_rules: 'id, insurance_company_id',
            outbox: 'client_event_id, status, created_at',
            meta: 'key',
        });
    }
}

export const db = new PharmaGestDb();
