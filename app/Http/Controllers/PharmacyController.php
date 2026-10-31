<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePharmacyRequest;
use App\Models\Pharmacy;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PharmacyController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('manage_pharmacies');

        return Inertia::render('Pharmacies', [
            'pharmacies' => Pharmacy::withCount(['sales', 'users'])->orderBy('name')->get(),
        ]);
    }

    public function store(StorePharmacyRequest $request)
    {
        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active', true);
        $pharmacy = Pharmacy::create($data);

        app(AuditLogger::class)->log('pharmacy.created', Pharmacy::class, $pharmacy->id, [], $pharmacy->toArray());

        return redirect()->back()->with('success', 'Pharmacie créée');
    }

    public function update(StorePharmacyRequest $request, Pharmacy $pharmacy)
    {
        $old = $pharmacy->toArray();
        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active', $pharmacy->is_active);
        $pharmacy->update($data);

        app(AuditLogger::class)->log('pharmacy.updated', Pharmacy::class, $pharmacy->id, $old, $pharmacy->fresh()?->toArray() ?? []);

        return redirect()->back()->with('success', 'Pharmacie mise à jour');
    }

    public function destroy(Pharmacy $pharmacy)
    {
        $this->authorize('delete', $pharmacy);

        $old = $pharmacy->toArray();
        $pharmacy->update(['is_active' => false]);

        app(AuditLogger::class)->log('pharmacy.disabled', Pharmacy::class, $pharmacy->id, $old, ['is_active' => false]);

        return redirect()->back()->with('success', 'Pharmacie désactivée');
    }

    /**
     * Change la pharmacie active (sélecteur de la Topbar). `null` = vue consolidée.
     */
    public function activate(Request $request)
    {
        $data = $request->validate([
            'pharmacy_id' => 'nullable|integer|exists:pharmacies,id',
        ]);

        if (! empty($data['pharmacy_id'])) {
            $allowed = $request->user()->pharmacies()->whereKey($data['pharmacy_id'])->exists();
            abort_unless($allowed || $request->user()->is_super_admin, 403);
            $request->session()->put('active_pharmacy_id', (int) $data['pharmacy_id']);
        } else {
            $request->session()->forget('active_pharmacy_id');
        }

        return redirect()->back();
    }
}
