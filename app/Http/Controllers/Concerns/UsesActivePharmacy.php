<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Pharmacy;
use Illuminate\Http\Request;

trait UsesActivePharmacy
{
    /**
     * Pharmacie active : celle choisie dans la Topbar (session), sinon la
     * première pharmacie rattachée à l'utilisateur, sinon la première créée.
     */
    protected function pharmacy(Request $request): Pharmacy
    {
        $user = $request->user();
        $activeId = $request->session()->get('active_pharmacy_id');

        if ($activeId && $user) {
            $active = $user->pharmacies()->where('pharmacies.id', $activeId)->first();
            if ($active) {
                return $active;
            }
        }

        return $user?->pharmacies()->first() ?? Pharmacy::query()->orderBy('id')->firstOrFail();
    }
}
