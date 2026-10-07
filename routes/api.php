<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\LookupController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\TicketController;
use App\Http\Controllers\TicketAttachmentController;
use App\Http\Controllers\TicketCommentController;
use App\Http\Controllers\TicketWorkReportController;
use Illuminate\Support\Facades\Route;

Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:10,1')->name('api.auth.login');

Route::middleware(['auth:sanctum', 'active', 'throttle:api'])->group(function (): void {
    Route::get('auth/user', [AuthController::class, 'user'])->name('api.auth.user');
    Route::post('auth/logout', [AuthController::class, 'logout'])->middleware('throttle:api-write')->name('api.auth.logout');

    Route::get('lookups', LookupController::class)->name('api.lookups');

    Route::get('tickets', [TicketController::class, 'index'])->name('api.tickets.index');
    Route::post('tickets', [TicketController::class, 'store'])->middleware('throttle:api-write')->name('api.tickets.store');

    Route::scopeBindings()->group(function (): void {
        Route::get('tickets/{ticket}', [TicketController::class, 'show'])->name('api.tickets.show');
        Route::patch('tickets/{ticket}', [TicketController::class, 'update'])->middleware('throttle:api-write')->name('api.tickets.update');
        Route::patch('tickets/{ticket}/status', [TicketController::class, 'status'])->middleware('throttle:api-write')->name('api.tickets.status');
        Route::post('tickets/{ticket}/assignments', [TicketController::class, 'assign'])->middleware('throttle:api-write')->name('api.tickets.assign');
        Route::post('tickets/{ticket}/technician-assignment', [TicketWorkReportController::class, 'assignTechnician'])->middleware('throttle:api-write')->name('api.tickets.assign-technician');
        Route::delete('tickets/{ticket}/assignments/current', [TicketController::class, 'unassign'])->middleware('throttle:api-write')->name('api.tickets.unassign');
        Route::get('tickets/{ticket}/work-reports', [TicketWorkReportController::class, 'index'])->name('api.tickets.work-reports.index');
        Route::post('tickets/{ticket}/work/start', [TicketWorkReportController::class, 'start'])->middleware('throttle:api-write')->name('api.tickets.work.start');
        Route::post('tickets/{ticket}/work/request-information', [TicketWorkReportController::class, 'requestInformation'])->middleware('throttle:api-write')->name('api.tickets.work.request-information');
        Route::post('tickets/{ticket}/work/complete', [TicketWorkReportController::class, 'complete'])->middleware('throttle:api-write')->name('api.tickets.work.complete');
        Route::post('tickets/{ticket}/work/approve', [TicketWorkReportController::class, 'approve'])->middleware('throttle:api-write')->name('api.tickets.work.approve');
        Route::post('tickets/{ticket}/work/send-back', [TicketWorkReportController::class, 'sendBack'])->middleware('throttle:api-write')->name('api.tickets.work.send-back');
        Route::get('tickets/{ticket}/comments', [TicketController::class, 'comments'])->name('api.tickets.comments.index');
        Route::post('tickets/{ticket}/comments', [TicketCommentController::class, 'store'])->middleware('throttle:api-write')->name('api.tickets.comments.store');
        Route::get('tickets/{ticket}/comments/{comment}', [TicketCommentController::class, 'show'])->name('api.tickets.comments.show');
        Route::get('tickets/{ticket}/attachments', [TicketController::class, 'attachments'])->name('api.tickets.attachments.index');
        Route::post('tickets/{ticket}/attachments', [TicketController::class, 'uploadAttachment'])->middleware('throttle:api-write')->name('api.tickets.attachments.store');
        Route::get('tickets/{ticket}/attachments/{attachment}', [TicketAttachmentController::class, 'show'])->name('api.tickets.attachments.show');
    });

    Route::get('notifications', [NotificationController::class, 'index'])->name('api.notifications.index');
    Route::get('notifications/unread-count', [NotificationController::class, 'unreadCount'])->name('api.notifications.unread-count');
    Route::patch('notifications/read-all', [NotificationController::class, 'readAll'])->middleware('throttle:api-write')->name('api.notifications.read-all');
    Route::patch('notifications/{notification}/read', [NotificationController::class, 'read'])->middleware('throttle:api-write')->name('api.notifications.read');
});
