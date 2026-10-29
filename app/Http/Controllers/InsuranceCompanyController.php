<?php

namespace App\Http\Controllers;

use App\Models\InsuranceCompany;
use Illuminate\Http\Request;
use Inertia\Inertia;

class InsuranceCompanyController extends Controller
{
    public function index()
    {
        return Inertia::render('Insurers', ['companies' => InsuranceCompany::orderBy('name')->get()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'code' => 'nullable|string|max:40',
            'contact' => 'nullable|string|max:120',
            'phone' => 'nullable|string|max:40',
            'email' => 'nullable|email|max:120',
        ]);
        $data['is_active'] = true;
        InsuranceCompany::create($data);
        return redirect()->back()->with('success', 'Assureur créé');
    }

    public function destroy(InsuranceCompany $insuranceCompany)
    {
        $insuranceCompany->delete();
        return redirect()->back()->with('success', 'Assureur supprimé');
    }
}
