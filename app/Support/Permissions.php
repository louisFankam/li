<?php

namespace App\Support;

/**
 * Central permission map for PharmaGest roles.
 *
 * Roles are stored per pharmacy on the pharmacy_user pivot (role column).
 * A user may have different roles on different pharmacies.
 */
class Permissions
{
    public const VIEW_DASHBOARD = 'view_dashboard';

    public const MANAGE_PRODUCTS = 'manage_products';

    public const MANAGE_STOCK = 'manage_stock';

    public const MANAGE_PURCHASES = 'manage_purchases';

    public const MANAGE_INVENTORY = 'manage_inventory';

    public const MANAGE_TRANSFERS = 'manage_transfers';

    public const MAKE_SALES = 'make_sales';

    public const APPLY_DISCOUNT = 'apply_discount';

    public const APPROVE_DISCOUNT = 'approve_discount';

    public const CANCEL_SALE = 'cancel_sale';

    public const MANAGE_CASH = 'manage_cash';

    public const MANAGE_INSURANCE = 'manage_insurance';

    public const MANAGE_USERS = 'manage_users';

    public const MANAGE_PHARMACIES = 'manage_pharmacies';

    public const MANAGE_SETTINGS = 'manage_settings';

    public const VIEW_REPORTS = 'view_reports';

    public const EXPORT_DATA = 'export_data';

    public const MANAGE_AUDIT = 'manage_audit';

    public static function all(): array
    {
        return [
            self::VIEW_DASHBOARD,
            self::MANAGE_PRODUCTS,
            self::MANAGE_STOCK,
            self::MANAGE_PURCHASES,
            self::MANAGE_INVENTORY,
            self::MANAGE_TRANSFERS,
            self::MAKE_SALES,
            self::APPLY_DISCOUNT,
            self::APPROVE_DISCOUNT,
            self::CANCEL_SALE,
            self::MANAGE_CASH,
            self::MANAGE_INSURANCE,
            self::MANAGE_USERS,
            self::MANAGE_PHARMACIES,
            self::MANAGE_SETTINGS,
            self::VIEW_REPORTS,
            self::EXPORT_DATA,
            self::MANAGE_AUDIT,
        ];
    }

    /**
     * Role -> allowed permissions.
     */
    public static function forRole(string $role): array
    {
        $all = self::all();

        return match ($role) {
            'administrateur', 'admin', 'administrateur_client' => $all,
            'responsable_regional' => [
                self::VIEW_DASHBOARD, self::MANAGE_PRODUCTS, self::MANAGE_STOCK, self::MANAGE_TRANSFERS,
                self::VIEW_REPORTS, self::EXPORT_DATA, self::MANAGE_AUDIT, self::APPROVE_DISCOUNT,
                self::CANCEL_SALE, self::MANAGE_INSURANCE,
            ],
            'gerant', 'gérant de pharmacie', 'gerant_pharmacie' => [
                self::VIEW_DASHBOARD, self::MANAGE_PRODUCTS, self::MANAGE_STOCK, self::MANAGE_PURCHASES,
                self::MANAGE_INVENTORY, self::MANAGE_TRANSFERS, self::MAKE_SALES, self::APPLY_DISCOUNT,
                self::APPROVE_DISCOUNT, self::CANCEL_SALE, self::MANAGE_CASH, self::MANAGE_INSURANCE,
                self::MANAGE_USERS, self::MANAGE_SETTINGS, self::VIEW_REPORTS, self::EXPORT_DATA,
            ],
            'pharmacien', 'responsable_delivrance' => [
                self::VIEW_DASHBOARD, self::MANAGE_PRODUCTS, self::MANAGE_STOCK, self::MAKE_SALES,
                self::APPLY_DISCOUNT, self::MANAGE_INSURANCE, self::VIEW_REPORTS,
            ],
            'vendeur', 'caissier', 'vendeur_caissier' => [
                self::VIEW_DASHBOARD, self::MAKE_SALES, self::APPLY_DISCOUNT, self::MANAGE_CASH,
            ],
            'gestionnaire_stock' => [
                self::VIEW_DASHBOARD, self::MANAGE_STOCK, self::MANAGE_PURCHASES, self::MANAGE_INVENTORY,
                self::MANAGE_TRANSFERS,
            ],
            'comptable', 'comptable_assurance', 'gestionnaire_assurance' => [
                self::VIEW_DASHBOARD, self::MANAGE_INSURANCE, self::VIEW_REPORTS, self::EXPORT_DATA,
                self::MANAGE_CASH,
            ],
            default => [self::VIEW_DASHBOARD],
        };
    }
}
