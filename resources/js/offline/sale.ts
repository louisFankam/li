import { getDb, nextOutboxSequence, type OfflinePayment, type OfflineSale, type OfflineSaleItem, type OutboxEvent } from './db';

export type OfflineSaleLineInput = {
    product_id: number;
    quantity: number;
};

export type OfflinePaymentInput = {
    method: string;
    amount: number;
    reference?: string | null;
};

export type OfflineSalePreview = {
    subtotal: number;
    discount_percent: number | null;
    discount_amount: number;
    total: number;
    client_part: number;
    insurer_part: number;
    lines: {
        product_id: number;
        name: string;
        quantity: number;
        unit: string | null;
        unit_price: number;
        total: number;
    }[];
};

export type OfflineCheckoutInput = {
    clientRequestId: string;
    pharmacyId: number;
    userId: number;
    customerId: number | null;
    insuranceCompanyId: number | null;
    discountPercent: number;
    discountAmount: number;
    discountReason: string;
    discountCap: { percent: number; amount: number };
    items: OfflineSaleLineInput[];
    payments: OfflinePaymentInput[];
};

const PAYMENT_METHODS = ['especes', 'mobile_money', 'carte', 'cheque', 'virement', 'credit'];

function moneyError(message: string): never {
    throw new Error(message);
}

async function calculatePreview(input: OfflineCheckoutInput): Promise<OfflineSalePreview> {
    const database = getDb();
    const productIds = [...new Set(input.items.map((item) => item.product_id))];
    const products = await database.products.bulkGet(productIds);
    const productMap = new Map(products.filter((product) => product !== undefined).map((product) => [product.id, product]));

    const lines = input.items.map((item) => {
        const product = productMap.get(item.product_id);
        if (!product || !product.is_active) {
            moneyError('Un produit du panier est introuvable ou inactif.');
        }

        const quantity = Number(item.quantity);
        if (!Number.isInteger(quantity) || quantity < 1) {
            moneyError('La quantité doit être au moins 1.');
        }

        return {
            product_id: product.id,
            name: product.name,
            quantity,
            unit: product.sale_unit ?? product.base_unit ?? 'boite',
            unit_price: product.sale_price,
            total: product.sale_price * quantity,
            category_id: product.category_id,
        };
    });

    const subtotal = lines.reduce((sum, line) => sum + line.total, 0);
    const percent: number | null = input.discountPercent > 0 ? Math.trunc(input.discountPercent) : null;
    let discountAmount = Math.max(0, Math.trunc(input.discountAmount));

    if (percent !== null) {
        discountAmount = Math.round((subtotal * percent) / 100);
    }

    const discountSettings = (await database.meta.get('discount_settings'))?.value as { absolute_max_percent?: number } | undefined;
    const absoluteMax = Number(discountSettings?.absolute_max_percent ?? 50);
    const absoluteMaxAmount = Math.floor((subtotal * absoluteMax) / 100);

    if (
        (percent ?? 0) > input.discountCap.percent ||
        discountAmount > input.discountCap.amount ||
        (percent !== null && percent > absoluteMax) ||
        discountAmount > absoluteMaxAmount
    ) {
        moneyError('Cette remise nécessite une approbation et ne peut pas être appliquée hors ligne.');
    }

    discountAmount = Math.max(0, Math.min(discountAmount, subtotal));
    const total = subtotal - discountAmount;

    if (total <= 0) {
        moneyError('La remise ne peut pas rendre la vente gratuite.');
    }

    let insurerPart = 0;
    if (input.insuranceCompanyId !== null) {
        const rules = await database.insurance_rules.where('insurance_company_id').equals(input.insuranceCompanyId).toArray();
        const defaultRule = rules.find((rule) => rule.product_id === null && rule.category_id === null);

        for (const line of lines) {
            const rule =
                rules.find((candidate) => candidate.product_id === line.product_id) ??
                rules.find((candidate) => candidate.category_id !== null && candidate.category_id === line.category_id) ??
                defaultRule;
            const lineInsurer = rule
                ? rule.type === 'percentage'
                    ? Math.round((line.total * rule.value) / 100)
                    : rule.value
                : Math.round(line.total * 0.8);

            insurerPart += Math.max(0, Math.min(lineInsurer, line.total));
        }

        insurerPart = subtotal > 0 ? Math.round((insurerPart * total) / subtotal) : 0;
        insurerPart = Math.max(0, Math.min(insurerPart, total));
    }

    return {
        subtotal,
        discount_percent: percent,
        discount_amount: discountAmount,
        total,
        client_part: total - insurerPart,
        insurer_part: insurerPart,
        lines: lines.map((line) => ({
            product_id: line.product_id,
            name: line.name,
            quantity: line.quantity,
            unit: line.unit,
            unit_price: line.unit_price,
            total: line.total,
        })),
    };
}

async function assertStock(input: OfflineCheckoutInput): Promise<void> {
    const database = getDb();
    const quantities = new Map<number, number>();

    for (const item of input.items) {
        quantities.set(item.product_id, (quantities.get(item.product_id) ?? 0) + Number(item.quantity));
    }

    for (const [productId, quantity] of quantities) {
        const stock = await database.stock_levels.get([input.pharmacyId, productId]);
        if (!stock || stock.quantity < quantity) {
            moneyError(`Stock insuffisant pour le produit #${productId} (disponible : ${stock?.quantity ?? 0}).`);
        }
    }
}

export async function previewOfflineSale(input: OfflineCheckoutInput): Promise<OfflineSalePreview> {
    const preview = await calculatePreview(input);
    await assertStock(input);

    return preview;
}

export async function checkoutOfflineSale(input: OfflineCheckoutInput): Promise<{ sale: OfflineSale; preview: OfflineSalePreview }> {
    const database = getDb();

    return database.transaction(
        'rw',
        [
            database.products,
            database.insurance_rules,
            database.stock_levels,
            database.local_sales,
            database.local_sale_items,
            database.local_payments,
            database.outbox,
            database.meta,
        ],
        async () => {
            const preview = await calculatePreview(input);
            await assertStock(input);

            if (input.payments.length === 0) {
                moneyError('Au moins un règlement est requis.');
            }

            const paid = input.payments.reduce((sum, payment) => sum + Number(payment.amount), 0);
            if (paid !== preview.client_part) {
                moneyError(`Le total des règlements (${paid} GNF) doit égaler la part client (${preview.client_part} GNF).`);
            }

            for (const payment of input.payments) {
                if (!PAYMENT_METHODS.includes(payment.method) || Number(payment.amount) < 0) {
                    moneyError('Moyen de paiement ou montant invalide.');
                }
            }

            const now = new Date().toISOString();
            const sale: OfflineSale = {
                client_request_id: input.clientRequestId,
                sale_number: `LOCAL-${input.pharmacyId}-${now.slice(0, 10).replaceAll('-', '')}-${input.clientRequestId.slice(0, 8).toUpperCase()}`,
                pharmacy_id: input.pharmacyId,
                user_id: input.userId,
                customer_id: input.customerId,
                insurance_company_id: input.insuranceCompanyId,
                subtotal: preview.subtotal,
                discount_amount: preview.discount_amount,
                discount_percent: preview.discount_percent,
                discount_reason: input.discountReason || null,
                total: preview.total,
                client_part: preview.client_part,
                insurer_part: preview.insurer_part,
                status: 'pending',
                server_sale_id: null,
                error: null,
                created_at: now,
            };

            await database.local_sales.add(sale);

            for (const line of preview.lines) {
                await database.local_sale_items.add({
                    client_request_id: input.clientRequestId,
                    ...line,
                });

                const stock = await database.stock_levels.get([input.pharmacyId, line.product_id]);
                if (!stock) {
                    moneyError('Stock local introuvable.');
                }

                await database.stock_levels.put({
                    ...stock,
                    quantity: stock.quantity - line.quantity,
                    version: stock.version + 1,
                });
            }

            for (const payment of input.payments) {
                await database.local_payments.add({
                    client_request_id: input.clientRequestId,
                    method: payment.method,
                    amount: Number(payment.amount),
                    reference: payment.reference ?? null,
                });
            }

            const sequence = await nextOutboxSequence(database);
            const event: OutboxEvent = {
                client_event_id: input.clientRequestId,
                type: 'sale.created',
                pharmacy_id: input.pharmacyId,
                payload: {
                    client_request_id: input.clientRequestId,
                    items: preview.lines.map((line) => ({
                        product_id: line.product_id,
                        quantity: line.quantity,
                        unit: line.unit,
                    })),
                    payments: input.payments.map((payment) => ({
                        method: payment.method,
                        amount: Number(payment.amount),
                        reference: payment.reference ?? null,
                    })),
                    customer_id: input.customerId,
                    insurance_company_id: input.insuranceCompanyId,
                    discount_percent: preview.discount_percent,
                    discount_amount: preview.discount_amount || null,
                    discount_reason: input.discountReason || null,
                },
                occurred_at: now,
                schema_version: 1,
                status: 'pending',
                attempts: 0,
                sequence,
                created_at: now,
            };

            await database.outbox.add(event);

            return { sale, preview };
        },
    );
}

export async function localSales(pharmacyId: number): Promise<OfflineSale[]> {
    const rows = await getDb().local_sales.where('pharmacy_id').equals(pharmacyId).toArray();

    return rows.sort((a, b) => b.created_at.localeCompare(a.created_at));
}

export async function localTicket(clientRequestId: string): Promise<{
    sale: OfflineSale | null;
    items: OfflineSaleItem[];
    payments: OfflinePayment[];
}> {
    const database = getDb();
    const [sale, items, payments] = await Promise.all([
        database.local_sales.get(clientRequestId),
        database.local_sale_items.where('client_request_id').equals(clientRequestId).toArray(),
        database.local_payments.where('client_request_id').equals(clientRequestId).toArray(),
    ]);

    return { sale: sale ?? null, items, payments };
}
