<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSupplierRequest;
use App\Models\Supplier;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SupplierController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('manage_purchases');

        return Inertia::render('Suppliers', [
            'suppliers' => Supplier::withCount('purchases')->orderBy('name')->get(),
        ]);
    }

    public function store(StoreSupplierRequest $request)
    {
        $supplier = Supplier::create($request->validated());

        app(AuditLogger::class)->log('supplier.created', Supplier::class, $supplier->id, [], $supplier->toArray());

        return redirect()->back()->with('success', 'Fournisseur créé');
    }

    public function update(StoreSupplierRequest $request, Supplier $supplier)
    {
        $old = $supplier->toArray();
        $supplier->update($request->validated());

        app(AuditLogger::class)->log('supplier.updated', Supplier::class, $supplier->id, $old, $supplier->fresh()?->toArray() ?? []);

        return redirect()->back()->with('success', 'Fournisseur mis à jour');
    }

    public function destroy(Supplier $supplier)
    {
        $this->authorize('manage_purchases');

        $old = $supplier->toArray();
        $supplier->delete();

        app(AuditLogger::class)->log('supplier.deleted', Supplier::class, $supplier->id, $old, []);

        return redirect()->back()->with('success', 'Fournisseur supprimé');
    }
}
