<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreInsurerRequest;
use App\Models\InsuranceCompany;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Inertia\Inertia;

class InsuranceCompanyController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('manage_insurance');

        return Inertia::render('Insurers', [
            'companies' => InsuranceCompany::withCount('customers')->orderBy('name')->get(),
        ]);
    }

    public function store(StoreInsurerRequest $request)
    {
        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active', true);
        $company = InsuranceCompany::create($data);

        app(AuditLogger::class)->log('insurer.created', InsuranceCompany::class, $company->id, [], $company->toArray());

        return redirect()->back()->with('success', 'Assureur créé');
    }

    public function update(StoreInsurerRequest $request, InsuranceCompany $insuranceCompany)
    {
        $old = $insuranceCompany->toArray();
        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active', $insuranceCompany->is_active);
        $insuranceCompany->update($data);

        app(AuditLogger::class)->log('insurer.updated', InsuranceCompany::class, $insuranceCompany->id, $old, $insuranceCompany->fresh()?->toArray() ?? []);

        return redirect()->back()->with('success', 'Assureur mis à jour');
    }

    public function destroy(InsuranceCompany $insuranceCompany)
    {
        $this->authorize('manage_insurance');

        $old = $insuranceCompany->toArray();
        $insuranceCompany->update(['is_active' => false]);

        app(AuditLogger::class)->log('insurer.disabled', InsuranceCompany::class, $insuranceCompany->id, $old, ['is_active' => false]);

        return redirect()->back()->with('success', 'Assureur désactivé');
    }
}
