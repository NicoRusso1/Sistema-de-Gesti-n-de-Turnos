<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListAdminActionLogsRequest;
use App\Http\Resources\AdminActionLogResource;
use App\Models\AdminActionLog;

class AdminActionLogController extends Controller
{
    private const DEFAULT_PER_PAGE = 15;

    public function index(ListAdminActionLogsRequest $request)
    {
        $logs = AdminActionLog::query()
            ->with(['action', 'superAdmin', 'affectedAdmin'])
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', self::DEFAULT_PER_PAGE));

        return AdminActionLogResource::collection($logs);
    }
}
