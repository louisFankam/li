<?php

namespace App\Services;

use App\Models\Pharmacy;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Contexte multi-sites : pharmacie active, sites accessibles, contrôle d'accès.
 *
 * Toute lecture/écriture métier doit passer par ce service pour garantir
 * qu'un utilisateur ne voit jamais les données d'une pharmacie étrangère.
 */
class PharmacyContext
{
    public function user(?Request $request = null): ?User
    {
        return ($request ?? request())->user();
    }

    public function isGlobalAdmin(?User $user = null): bool
    {
        $user ??= $this->user();

        return $user?->is_super_admin === true;
    }

    /**
     * @return array<int>
     */
    public function accessibleIds(?Request $request = null): array
    {
        $user = $this->user($request);
        if (! $user) {
            return [];
        }

        if ($this->isGlobalAdmin($user)) {
            return Pharmacy::query()->pluck('id')->map(fn ($id) => (int) $id)->all();
        }

        return $user->pharmacies()->pluck('pharmacies.id')->map(fn ($id) => (int) $id)->all();
    }

    public function active(?Request $request = null): ?Pharmacy
    {
        $request ??= request();
        $user = $this->user($request);
        if (! $user) {
            return null;
        }

        $ids = $this->accessibleIds($request);
        if ($ids === []) {
            return null;
        }

        $activeId = $request->session()->get('active_pharmacy_id');
        if ($activeId && in_array((int) $activeId, $ids, true)) {
            return Pharmacy::query()->find((int) $activeId);
        }

        return Pharmacy::query()->whereIn('id', $ids)->orderBy('id')->first();
    }

    public function requireActive(?Request $request = null): Pharmacy
    {
        $pharmacy = $this->active($request);
        abort_if($pharmacy === null, 403, 'Aucune pharmacie accessible pour cet utilisateur.');

        return $pharmacy;
    }

    public function canAccess(int|Pharmacy $pharmacy, ?Request $request = null): bool
    {
        $id = $pharmacy instanceof Pharmacy ? $pharmacy->id : $pharmacy;

        return in_array((int) $id, $this->accessibleIds($request), true);
    }

    /**
     * Vérifie que le modèle appartient à une pharmacie accessible.
     * Retourne 404 (et non 403) pour ne pas révéler l'existence de la ressource.
     */
    public function guard(Model $model, string $column = 'pharmacy_id', ?Request $request = null): void
    {
        $id = $model->getAttribute($column);
        abort_unless($id !== null && $this->canAccess((int) $id, $request), 404);
    }

    /**
     * Vérifie qu'au moins une des colonnes pharmacie est accessible.
     */
    public function guardAny(Model $model, array $columns, ?Request $request = null): void
    {
        foreach ($columns as $column) {
            $id = $model->getAttribute($column);
            if ($id !== null && $this->canAccess((int) $id, $request)) {
                return;
            }
        }

        abort(404);
    }
}
