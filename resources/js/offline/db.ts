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
    is_active: boolean;
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
    is_active: boolean;
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
    status: 'pending' | 'sent' | 'failed' | 'conflict' | 'rejected';
    attempts: number;
    sequence: number;
    created_at: string;
}

export interface OfflineSale {
    client_request_id: string;
    sale_number: string;
    pharmacy_id: number;
    user_id: number;
    customer_id: number | null;
    insurance_company_id: number | null;
    subtotal: number;
    discount_amount: number;
    discount_percent: number | null;
    discount_reason: string | null;
    total: number;
    client_part: number;
    insurer_part: number;
    status: 'pending' | 'synced' | 'failed' | 'conflict' | 'rejected';
    server_sale_id: number | null;
    error: string | null;
    created_at: string;
}

export interface OfflineSaleItem {
    id?: number;
    client_request_id: string;
    product_id: number;
    name: string;
    quantity: number;
    unit: string | null;
    unit_price: number;
    total: number;
}

export interface OfflinePayment {
    id?: number;
    client_request_id: string;
    method: string;
    amount: number;
    reference: string | null;
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
    local_sales!: Table<OfflineSale, string>;
    local_sale_items!: Table<OfflineSaleItem, number>;
    local_payments!: Table<OfflinePayment, number>;
    outbox!: Table<OutboxEvent, string>;
    meta!: Table<MetaValue, string>;

    constructor(name: string) {
        super(name);
        this.version(1).stores({
            products: 'id, code, barcode, name',
            stock_levels: '[pharmacy_id+product_id], pharmacy_id, product_id',
            customers: 'id, name',
            insurers: 'id, name',
            insurance_rules: 'id, insurance_company_id',
            outbox: 'client_event_id, status, created_at',
            meta: 'key',
        });
        this.version(2).stores({
            outbox: 'client_event_id, status, created_at, [status+created_at]',
        });
        this.version(3).stores({
            outbox: 'client_event_id, status, created_at, [status+created_at], sequence',
            local_sales: 'client_request_id, status, created_at, [status+created_at]',
            local_sale_items: '++id, client_request_id, product_id',
            local_payments: '++id, client_request_id',
        });
    }
}

export function databaseName(userId: number): string {
    return `pharmagest-user-${userId}`;
}

let currentUserId: number | null = null;
let currentDatabase: PharmaGestDb | null = null;

export async function openUserDatabase(userId: number): Promise<PharmaGestDb> {
    if (currentDatabase !== null && currentUserId === userId) {
        return currentDatabase;
    }

    if (currentDatabase !== null) {
        currentDatabase.close();
    }

    const database = new PharmaGestDb(databaseName(userId));
    await database.open();
    currentUserId = userId;
    currentDatabase = database;

    return database;
}

export async function nextOutboxSequence(database: PharmaGestDb): Promise<number> {
    const meta = await database.meta.get('outbox_sequence');
    const next = (typeof meta?.value === 'number' ? meta.value : 0) + 1;
    await database.meta.put({ key: 'outbox_sequence', value: next });

    return next;
}

export function getDb(): PharmaGestDb {
    if (currentDatabase === null) {
        throw new Error('La base locale doit être ouverte pour un utilisateur connecté.');
    }

    return currentDatabase;
}

export function closeUserDatabase(): void {
    currentDatabase?.close();
    currentDatabase = null;
    currentUserId = null;
}
