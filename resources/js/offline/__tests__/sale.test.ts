import Dexie from 'dexie';
import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import { closeUserDatabase, databaseName, getDb, openUserDatabase } from '../db';
import { checkoutOfflineSale, previewOfflineSale, type OfflineCheckoutInput } from '../sale';

const USER_ID = 910_001;
const PHARMACY_ID = 51;
const PRODUCT_ID = 501;
const INSURER_ID = 91;

function input(overrides: Partial<OfflineCheckoutInput> = {}): OfflineCheckoutInput {
    return {
        clientRequestId: '11111111-1111-4111-8111-111111111111',
        pharmacyId: PHARMACY_ID,
        userId: USER_ID,
        customerId: null,
        insuranceCompanyId: null,
        discountPercent: 0,
        discountAmount: 0,
        discountReason: '',
        discountCap: { percent: 5, amount: 10_000 },
        items: [{ product_id: PRODUCT_ID, quantity: 1 }],
        payments: [{ method: 'especes', amount: 5_000 }],
        ...overrides,
    };
}

describe('offline sale domain', () => {
    beforeEach(async () => {
        await openUserDatabase(USER_ID);
        const database = getDb();
        await database.products.put({
            id: PRODUCT_ID,
            code: 'TEST-001',
            barcode: '1234567890123',
            name: 'Amoxicilline',
            generic_name: 'Amoxicilline',
            form: 'gélule',
            dosage: '500 mg',
            laboratory: 'Labo Test',
            category_id: null,
            base_unit: 'boite',
            sale_unit: 'boite',
            conversion_factor: 1,
            purchase_price: 2_500,
            sale_price: 5_000,
            alert_threshold: 5,
            is_active: true,
            version: 1,
        });
        await database.stock_levels.put({
            pharmacy_id: PHARMACY_ID,
            product_id: PRODUCT_ID,
            quantity: 10,
            version: 1,
        });
        await database.insurance_rules.put({
            id: 1,
            insurance_company_id: INSURER_ID,
            product_id: null,
            category_id: null,
            type: 'percentage',
            value: 80,
            version: 1,
        });
        await database.meta.put({
            key: 'discount_settings',
            value: { absolute_max_percent: 50 },
        });
    });

    afterEach(async () => {
        closeUserDatabase();
        await Dexie.delete(databaseName(USER_ID));
    });

    it('creates sale, stock movement, payments and outbox atomically', async () => {
        const { sale } = await checkoutOfflineSale(input());
        const database = getDb();

        expect(sale.status).toBe('pending');
        expect(await database.local_sales.count()).toBe(1);
        expect(await database.local_sale_items.count()).toBe(1);
        expect(await database.local_payments.count()).toBe(1);
        expect(await database.outbox.count()).toBe(1);
        expect((await database.stock_levels.get([PHARMACY_ID, PRODUCT_ID]))?.quantity).toBe(9);

        const event = await database.outbox.toCollection().first();
        expect(event?.type).toBe('sale.created');
        expect(event?.sequence).toBe(1);
        expect(event?.payload['client_request_id']).toBe(sale.client_request_id);
    });

    it('rejects insufficient local stock without partial writes', async () => {
        await expect(
            checkoutOfflineSale(
                input({
                    items: [{ product_id: PRODUCT_ID, quantity: 11 }],
                    payments: [{ method: 'especes', amount: 55_000 }],
                }),
            ),
        ).rejects.toThrow('Stock insuffisant');

        const database = getDb();
        expect(await database.local_sales.count()).toBe(0);
        expect(await database.outbox.count()).toBe(0);
        expect((await database.stock_levels.get([PHARMACY_ID, PRODUCT_ID]))?.quantity).toBe(10);
    });

    it('rejects discounts requiring approval offline', async () => {
        await expect(
            previewOfflineSale(
                input({
                    discountPercent: 10,
                    discountReason: 'Trop élevée',
                }),
            ),
        ).rejects.toThrow('approbation');
    });

    it('shares a discount proportionally with the insurer', async () => {
        const preview = await previewOfflineSale(
            input({
                items: [{ product_id: PRODUCT_ID, quantity: 2 }],
                insuranceCompanyId: INSURER_ID,
                discountPercent: 20,
                discountReason: 'Geste commercial',
                discountCap: { percent: 25, amount: 250_000 },
                payments: [{ method: 'especes', amount: 1_600 }],
            }),
        );

        expect(preview.total).toBe(8_000);
        expect(preview.insurer_part).toBe(6_400);
        expect(preview.client_part).toBe(1_600);
    });
});
