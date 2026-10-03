<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminSearchRequest;
use App\Http\Requests\Admin\SaveCategoryRequest;
use App\Models\TicketCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function index(AdminSearchRequest $request): View
    {
        $this->authorize('viewAny', TicketCategory::class);
        $filters = $request->validated();
        $items = TicketCategory::query()->when($filters['search'] ?? null, fn ($q, $search) => $q->where('name', 'like', "%{$search}%"))
            ->withCount('tickets')->orderBy('name')->paginate(25)->withQueryString();

        return view('admin.master-data.index', [
            'title' => 'Ticket categories', 'items' => $items, 'filters' => $filters,
            'createRoute' => 'admin.categories.create', 'editRoute' => 'admin.categories.edit', 'kind' => 'category',
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', TicketCategory::class);

        return view('admin.master-data.form', ['title' => 'Ticket category', 'item' => new TicketCategory, 'kind' => 'category', 'storeRoute' => 'admin.categories.store', 'creating' => true]);
    }

    public function store(SaveCategoryRequest $request): RedirectResponse
    {
        TicketCategory::query()->create($request->validated() + ['is_active' => true]);

        return redirect()->route('admin.categories.index')->with('status', 'Category created.');
    }

    public function edit(TicketCategory $category): View
    {
        $this->authorize('update', $category);

        return view('admin.master-data.form', ['title' => 'Ticket category', 'item' => $category, 'kind' => 'category', 'storeRoute' => 'admin.categories.update', 'creating' => false]);
    }

    public function update(SaveCategoryRequest $request, TicketCategory $category): RedirectResponse
    {
        $category->fill($request->validated())->save();

        return redirect()->route('admin.categories.index')->with('status', 'Category updated.');
    }
}
