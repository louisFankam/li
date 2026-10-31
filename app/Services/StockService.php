<?php

namespace App\Services;

use App\Models\Pharmacy;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\StockMovement;
use Illuminate\Validation\ValidationException;

/**
 * Point d'entrée unique pour toute mutation de stock.
 *
 * Invariants :
 * - le stock n'est jamais négatif ;
 * - chaque mouvement est lié à une pharmacie, un produit et un utilisateur ;
 * - la quantité est signée (entrée > 0, sortie < 0).
 */
class StockService
{
    public function apply(
        Pharmacy|int $pharmacy,
        Product|int $product,
        int $delta,
        string $type,
        ?string $reason = null,
        ?int $userId = null,
        ?string $referenceType = null,
        ?int $referenceId = null,
    ): StockMovement {
        $pharmacyId = $pharmacy instanceof Pharmacy ? $pharmacy->id : $pharmacy;
        $productId = $product instanceof Product ? $product->id : $product;

        if ($delta === 0) {
            throw ValidationException::withMessages(['quantity' => 'Le mouvement de stock ne peut pas être nul.']);
        }

        $stock = StockLevel::query()
            ->where('pharmacy_id', $pharmacyId)
            ->where('product_id', $productId)
            ->lockForUpdate()
            ->first();

        $current = $stock !== null ? $stock->quantity : 0;
        $new = $current + $delta;

        if ($new < 0) {
            throw ValidationException::withMessages([
                'quantity' => 'Stock insuffisant (disponible : '.$current.').',
            ]);
        }

        if ($stock) {
            $stock->update(['quantity' => $new]);
        } else {
            StockLevel::create([
                'pharmacy_id' => $pharmacyId,
                'product_id' => $productId,
                'quantity' => $new,
            ]);
        }

        // Les mises à jour en masse ne déclenchent pas les observateurs :
        // on force l'incrément de version utilisé par le pull offline.
        StockLevel::query()
            ->where('pharmacy_id', $pharmacyId)
            ->where('product_id', $productId)
            ->increment('version');

        return StockMovement::create([
            'pharmacy_id' => $pharmacyId,
            'product_id' => $productId,
            'type' => $type,
            'quantity' => $delta,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'reason' => $reason,
            'user_id' => $userId,
        ]);
    }

    public function setCount(
        Pharmacy|int $pharmacy,
        Product|int $product,
        int $counted,
        ?int $userId = null,
        ?string $reason = null,
    ): ?StockMovement {
        $pharmacyId = $pharmacy instanceof Pharmacy ? $pharmacy->id : $pharmacy;
        $productId = $product instanceof Product ? $product->id : $product;

        $stock = StockLevel::query()
            ->where('pharmacy_id', $pharmacyId)
            ->where('product_id', $productId)
            ->lockForUpdate()
            ->first();

        $theoretical = $stock !== null ? $stock->quantity : 0;
        $delta = $counted - $theoretical;

        if ($delta === 0) {
            return null;
        }

        return $this->apply(
            $pharmacyId,
            $productId,
            $delta,
            'inventaire',
            $reason ?? 'Comptage physique',
            $userId
        );
    }

    public function available(Pharmacy|int $pharmacy, Product|int $product): int
    {
        $pharmacyId = $pharmacy instanceof Pharmacy ? $pharmacy->id : $pharmacy;
        $productId = $product instanceof Product ? $product->id : $product;

        return (int) StockLevel::query()
            ->where('pharmacy_id', $pharmacyId)
            ->where('product_id', $productId)
            ->value('quantity');
    }
}
