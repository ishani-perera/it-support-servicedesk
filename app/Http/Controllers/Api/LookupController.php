<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\TicketCategory;
use App\Models\TicketPriority;
use App\Models\TicketStatus;
use Illuminate\Http\JsonResponse;

class LookupController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $this->authorize('viewAny', Department::class);
        $this->authorize('viewAny', TicketCategory::class);
        $this->authorize('viewAny', TicketPriority::class);
        $this->authorize('viewAny', TicketStatus::class);

        return response()->json(['data' => [
            'departments' => Department::query()->active()->orderBy('name')->get(['id', 'name']),
            'categories' => TicketCategory::query()->active()->orderBy('name')->get(['id', 'name']),
            'priorities' => TicketPriority::query()->active()->ordered()->get(['id', 'name', 'level']),
            'statuses' => TicketStatus::query()->active()->ordered()->get(['id', 'name', 'slug', 'color']),
        ]]);
    }
}
