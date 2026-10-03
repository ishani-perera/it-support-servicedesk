<?php

use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\CategoryController as AdminCategoryController;
use App\Http\Controllers\Admin\DepartmentController as AdminDepartmentController;
use App\Http\Controllers\Admin\PriorityController as AdminPriorityController;
use App\Http\Controllers\Admin\ReportController as AdminReportController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\EmployeeDashboardController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SupportDashboardController;
use App\Http\Controllers\TicketAttachmentController;
use App\Http\Controllers\TicketCommentController;
use App\Http\Controllers\TicketController;
use App\Services\TicketQueryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web routes (session authentication, CSRF-protected by the "web" group)
|--------------------------------------------------------------------------
|
| Phase 03 intentionally defines only the authentication/authorization
| foundation. The ticket/admin routes below are minimal "authorization
| boundary" endpoints: they exist so the Policies are enforced and tested over
| real HTTP. Dashboards, ticket CRUD and the REST API are later phases.
|
| Rules for every route added in future phases:
|   - behind ['auth', 'active'] (or auth:sanctum + active for the API);
|   - route model binding is NOT authorization: call $this->authorize();
|   - nested resources use ->scopeBindings();
|   - role:* middleware is a coarse gate only, never the sole check.
*/

Route::get('/', function () {
    return view('welcome');
});

/* ----- Guests ----- */
Route::middleware('guest')->group(function () {
    Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store'])->name('login.store');

    Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('password.email');

    Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('reset-password', [NewPasswordController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('password.store');
});

/* ----- Any signed-in, active user ----- */
Route::middleware(['auth', 'active'])->group(function () {
    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    // Landing page after login (placeholder — the role dashboards come later).
    Route::get('home', function (Request $request) {
        return match (true) {
            $request->user()->isEmployee() => app(EmployeeDashboardController::class)($request),
            $request->user()->isSupport() => app(SupportDashboardController::class)($request, app(TicketQueryService::class)),
            default => redirect()->route('admin.dashboard'),
        };
    })->name('home');

    Route::middleware('role:support')->prefix('support')->name('support.')->group(function () {
        Route::get('dashboard', SupportDashboardController::class)->name('dashboard');
    });

    Route::middleware('role:employee')->group(function () {
        Route::get('dashboard', EmployeeDashboardController::class)->name('employee.dashboard');
        Route::get('tickets/create', [TicketController::class, 'create'])->name('tickets.create');
    });

    Route::patch('profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('password', [PasswordController::class, 'update'])->name('password.update');

    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::patch('notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::patch('notifications/{notification}/read', [NotificationController::class, 'read'])->name('notifications.read');
    Route::get('notifications/{notification}/open', [NotificationController::class, 'open'])->name('notifications.open');

    // Record-level authorization boundary (Policies). {ticket} etc. are bound
    // by id, then authorized — never trusted.
    Route::get('tickets', [TicketController::class, 'index'])->name('tickets.index');
    Route::post('tickets', [TicketController::class, 'store'])->name('tickets.store');
    Route::get('tickets/{ticket}', [TicketController::class, 'show'])->name('tickets.show');
    Route::patch('tickets/{ticket}', [TicketController::class, 'update'])->name('tickets.update');
    Route::patch('tickets/{ticket}/status', [TicketController::class, 'status'])->name('tickets.status');
    Route::post('tickets/{ticket}/assignments', [TicketController::class, 'assign'])->name('tickets.assignments.store');
    Route::delete('tickets/{ticket}/assignments/current', [TicketController::class, 'unassign'])->name('tickets.assignments.destroy');
    Route::post('tickets/{ticket}/comments', [TicketCommentController::class, 'store'])->name('tickets.comments.store');
    Route::post('tickets/{ticket}/attachments', [TicketAttachmentController::class, 'store'])->name('tickets.attachments.store');

    Route::scopeBindings()->group(function () {
        Route::get('tickets/{ticket}/comments/{comment}', [TicketCommentController::class, 'show'])
            ->name('tickets.comments.show');
        Route::get('tickets/{ticket}/attachments/{attachment}', [TicketAttachmentController::class, 'show'])
            ->name('tickets.attachments.show');
    });
});

/* ----- Admin only ----- */
Route::middleware(['auth', 'active', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', AdminDashboardController::class)->name('dashboard');
    Route::get('users', [AdminUserController::class, 'index'])->name('users.index');
    Route::get('users/create', [AdminUserController::class, 'create'])->name('users.create');
    Route::post('users', [AdminUserController::class, 'store'])->name('users.store');
    Route::get('users/{user}', [AdminUserController::class, 'show'])->name('users.show');
    Route::get('users/{user}/edit', [AdminUserController::class, 'edit'])->name('users.edit');
    Route::patch('users/{user}', [AdminUserController::class, 'update'])->name('users.update');
    Route::patch('users/{user}/status', [AdminUserController::class, 'status'])->name('users.status');

    Route::get('departments', [AdminDepartmentController::class, 'index'])->name('departments.index');
    Route::get('departments/create', [AdminDepartmentController::class, 'create'])->name('departments.create');
    Route::post('departments', [AdminDepartmentController::class, 'store'])->name('departments.store');
    Route::get('departments/{department}/edit', [AdminDepartmentController::class, 'edit'])->name('departments.edit');
    Route::patch('departments/{department}', [AdminDepartmentController::class, 'update'])->name('departments.update');

    Route::get('categories', [AdminCategoryController::class, 'index'])->name('categories.index');
    Route::get('categories/create', [AdminCategoryController::class, 'create'])->name('categories.create');
    Route::post('categories', [AdminCategoryController::class, 'store'])->name('categories.store');
    Route::get('categories/{category}/edit', [AdminCategoryController::class, 'edit'])->name('categories.edit');
    Route::patch('categories/{category}', [AdminCategoryController::class, 'update'])->name('categories.update');

    Route::get('priorities', [AdminPriorityController::class, 'index'])->name('priorities.index');
    Route::get('priorities/{priority}/edit', [AdminPriorityController::class, 'edit'])->name('priorities.edit');
    Route::patch('priorities/{priority}', [AdminPriorityController::class, 'update'])->name('priorities.update');

    Route::get('statuses', [AdminPriorityController::class, 'statuses'])->name('statuses.index');
    Route::get('reports', [AdminReportController::class, 'index'])->name('reports.index');
    Route::get('reports/export', [AdminReportController::class, 'export'])->name('reports.export');
});
