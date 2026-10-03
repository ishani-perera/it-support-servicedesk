<?php

use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\EmployeeDashboardController;
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
            default => view('home'),
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
    Route::get('users', [AdminUserController::class, 'index'])->name('users.index');
    Route::patch('users/{user}', [AdminUserController::class, 'update'])->name('users.update');
});
