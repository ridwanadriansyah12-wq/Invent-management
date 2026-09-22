<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index()
    {
        $categories = Category::withCount('items')->orderBy('name')->get();
        return view('categories.index', compact('categories'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'        => ['required', 'string', 'max:100', 'unique:categories,name'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        Category::create($validated);
        return back()->with('success', 'Kategori berhasil ditambahkan.');
    }

    public function update(Request $request, Category $category)
    {
        $validated = $request->validate([
            'name'        => ['required', 'string', 'max:100', "unique:categories,name,{$category->id}"],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        $category->update($validated);
        return back()->with('success', 'Kategori berhasil diperbarui.');
    }

    public function destroy(Category $category)
    {
        if ($category->items()->exists()) {
            return back()->with('error', 'Tidak bisa menghapus kategori yang memiliki barang.');
        }

        $category->delete();
        return back()->with('success', 'Kategori berhasil dihapus.');
    }
}
