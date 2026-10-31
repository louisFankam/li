<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCustomerRequest;
use App\Models\Customer;
use App\Models\InsuranceCompany;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('make_sales');

        return Inertia::render('Customers', [
            'customers' => Customer::with('insuranceCompany')->orderBy('name')->get(),
            'insurers' => InsuranceCompany::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(StoreCustomerRequest $request)
    {
        $customer = Customer::create($request->validated());

        app(AuditLogger::class)->log('customer.created', Customer::class, $customer->id, [], $customer->toArray());

        return redirect()->back()->with('success', 'Client créé');
    }

    public function update(StoreCustomerRequest $request, Customer $customer)
    {
        $old = $customer->toArray();
        $customer->update($request->validated());

        app(AuditLogger::class)->log('customer.updated', Customer::class, $customer->id, $old, $customer->fresh()?->toArray() ?? []);

        return redirect()->back()->with('success', 'Client mis à jour');
    }

    public function destroy(Customer $customer)
    {
        $this->authorize('make_sales');

        $old = $customer->toArray();
        $customer->delete();

        app(AuditLogger::class)->log('customer.deleted', Customer::class, $customer->id, $old, []);

        return redirect()->back()->with('success', 'Client supprimé');
    }
}
