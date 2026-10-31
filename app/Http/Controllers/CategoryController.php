<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCategoryRequest;
use App\Models\Category;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CategoryController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('manage_stock');

        return Inertia::render('Categories', [
            'categories' => Category::withCount('products')->orderBy('name')->get(),
        ]);
    }

    public function store(StoreCategoryRequest $request)
    {
        $category = Category::create($request->validated());

        app(AuditLogger::class)->log('category.created', Category::class, $category->id, [], $category->toArray());

        return redirect()->back()->with('success', 'Catégorie créée');
    }

    public function update(StoreCategoryRequest $request, Category $category)
    {
        $old = $category->toArray();
        $category->update($request->validated());

        app(AuditLogger::class)->log('category.updated', Category::class, $category->id, $old, $category->fresh()?->toArray() ?? []);

        return redirect()->back()->with('success', 'Catégorie mise à jour');
    }

    public function destroy(Category $category)
    {
        $this->authorize('manage_stock');

        $old = $category->toArray();
        $category->delete();

        app(AuditLogger::class)->log('category.deleted', Category::class, $category->id, $old, []);

        return redirect()->back()->with('success', 'Catégorie supprimée');
    }
}
