<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminSearchRequest;
use App\Http\Requests\Admin\SaveDepartmentRequest;
use App\Models\Department;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class DepartmentController extends Controller
{
    public function index(AdminSearchRequest $request): View
    {
        $this->authorize('viewAny', Department::class);
        $filters = $request->validated();
        $departments = Department::query()->when($filters['search'] ?? null, fn ($q, $search) => $q->where('name', 'like', "%{$search}%"))
            ->withCount(['users', 'tickets'])->orderBy('name')->paginate(25)->withQueryString();

        return view('admin.master-data.index', [
            'title' => 'Departments', 'items' => $departments, 'filters' => $filters,
            'createRoute' => 'admin.departments.create', 'editRoute' => 'admin.departments.edit', 'kind' => 'department',
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Department::class);

        return view('admin.master-data.form', ['title' => 'Department', 'item' => new Department, 'kind' => 'department', 'storeRoute' => 'admin.departments.store', 'creating' => true]);
    }

    public function store(SaveDepartmentRequest $request): RedirectResponse
    {
        Department::query()->create($request->validated() + ['is_active' => true]);

        return redirect()->route('admin.departments.index')->with('status', 'Department created.');
    }

    public function edit(Department $department): View
    {
        $this->authorize('update', $department);

        return view('admin.master-data.form', ['title' => 'Department', 'item' => $department, 'kind' => 'department', 'storeRoute' => 'admin.departments.update', 'creating' => false]);
    }

    public function update(SaveDepartmentRequest $request, Department $department): RedirectResponse
    {
        $department->fill($request->validated())->save();

        return redirect()->route('admin.departments.index')->with('status', 'Department updated.');
    }
}
