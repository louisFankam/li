import { getDb } from './db';

export type OfflineCatalogProduct = {
    id: number;
    name: string;
    generic_name: string | null;
    barcode: string | null;
    category: string | null;
    labo: string;
    price: string;
    price_raw: number;
    stock: number;
    alert_threshold: number;
    tag: string | null;
};

export async function loadOfflineCatalog(pharmacyId: number): Promise<OfflineCatalogProduct[]> {
    const database = getDb();
    const [products, stockLevels] = await Promise.all([
        database.products.toArray(),
        database.stock_levels.where('pharmacy_id').equals(pharmacyId).toArray(),
    ]);
    const stocks = new Map(stockLevels.map((stock) => [stock.product_id, stock.quantity]));

    return products
        .filter((product) => product.is_active)
        .map((product) => {
            const stock = stocks.get(product.id) ?? 0;

            return {
                id: product.id,
                name: product.name,
                generic_name: product.generic_name,
                barcode: product.barcode,
                category: null,
                labo: [product.laboratory, product.form].filter(Boolean).join(' · ') || 'Médicament',
                price: product.sale_price.toLocaleString('fr-FR') + ' GNF',
                price_raw: product.sale_price,
                stock,
                alert_threshold: product.alert_threshold,
                tag: stock === 0 ? 'Rupture — bloqué' : stock < product.alert_threshold ? 'Stock faible' : null,
            };
        })
        .sort((a, b) => a.name.localeCompare(b.name));
}
