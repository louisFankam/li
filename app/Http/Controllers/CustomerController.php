<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\InsuranceCompany;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CustomerController extends Controller
{
    public function index()
    {
        return Inertia::render('Customers', [
            'customers' => Customer::with('insuranceCompany')->orderBy('name')->get(),
            'insurers' => InsuranceCompany::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'phone' => 'nullable|string|max:40',
            'email' => 'nullable|email|max:120',
            'insurance_company_id' => 'nullable|exists:insurance_companies,id',
        ]);
        Customer::create($data);
        return redirect()->back()->with('success', 'Client créé');
    }

    public function destroy(Customer $customer)
    {
        $customer->delete();
        return redirect()->back()->with('success', 'Client supprimé');
    }
}
