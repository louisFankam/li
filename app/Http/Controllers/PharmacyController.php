<?php

namespace App\Http\Controllers;

use App\Models\Pharmacy;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PharmacyController extends Controller
{
    public function index()
    {
        return Inertia::render('Pharmacies', ['pharmacies' => Pharmacy::orderBy('name')->get()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'city' => 'nullable|string|max:120',
            'address' => 'nullable|string',
            'phone' => 'nullable|string|max:40',
            'is_active' => 'boolean',
        ]);
        $data['is_active'] = $request->boolean('is_active', true);
        Pharmacy::create($data);
        return redirect()->back()->with('success', 'Pharmacie créée');
    }

    public function update(Request $request, Pharmacy $pharmacy)
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'city' => 'nullable|string|max:120',
            'address' => 'nullable|string',
            'phone' => 'nullable|string|max:40',
            'is_active' => 'boolean',
        ]);
        $data['is_active'] = $request->boolean('is_active', $pharmacy->is_active);
        $pharmacy->update($data);
        return redirect()->back()->with('success', 'Pharmacie mise à jour');
    }

    public function destroy(Pharmacy $pharmacy)
    {
        $pharmacy->delete();
        return redirect()->back()->with('success', 'Pharmacie supprimée');
    }
}
