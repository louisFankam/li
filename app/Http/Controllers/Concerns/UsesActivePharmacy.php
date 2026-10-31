<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Pharmacy;
use App\Services\PharmacyContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

trait UsesActivePharmacy
{
    protected function context(): PharmacyContext
    {
        return app(PharmacyContext::class);
    }

    /**
     * Pharmacie active : celle choisie dans la Topbar (session), sinon la
     * première pharmacie accessible. Abandonne si l'utilisateur n'a aucun site.
     */
    protected function pharmacy(Request $request): Pharmacy
    {
        return $this->context()->requireActive($request);
    }

    /**
     * Vérifie qu'un modèle appartient à une pharmacie accessible (404 sinon).
     */
    protected function guardPharmacy(Model $model, string $column = 'pharmacy_id'): void
    {
        $this->context()->guard($model, $column);
    }

    protected function guardAnyPharmacy(Model $model, array $columns): void
    {
        $this->context()->guardAny($model, $columns);
    }
}
