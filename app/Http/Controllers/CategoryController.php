<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CategoryController extends Controller
{
    public function index()
    {
        return Inertia::render('Categories', ['categories' => Category::orderBy('name')->get()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate(['name' => 'required|string|max:120|unique:categories,name']);
        Category::create($data);
        return redirect()->back()->with('success', 'Catégorie créée');
    }

    public function update(Request $request, Category $category)
    {
        $data = $request->validate(['name' => 'required|string|max:120|unique:categories,name,' . $category->id]);
        $category->update($data);
        return redirect()->back()->with('success', 'Catégorie mise à jour');
    }

    public function destroy(Category $category)
    {
        $category->delete();
        return redirect()->back()->with('success', 'Catégorie supprimée');
    }
}
