<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\StaffNotificationResource;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $this->requireStaff($request);

        $perPage = min(100, max(1, (int) $request->integer('per_page', 20)));
        $page = max(1, (int) $request->integer('page', 1));

        $paginator = $user->notifications()->latest()->paginate($perPage, ['*'], 'page', $page);

        return ApiResponse::success(
            StaffNotificationResource::collection($paginator->getCollection())->resolve($request),
            [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'unread_count' => $user->unreadNotifications()->count(),
            ],
        );
    }

    public function markRead(Request $request, string $notification): JsonResponse
    {
        $user = $this->requireStaff($request);

        $row = $user->notifications()->whereKey($notification)->first();
        if ($row === null) {
            return ApiResponse::error('resource.not_found', 'Notification not found.', 404);
        }

        $row->markAsRead();

        return ApiResponse::success(
            (new StaffNotificationResource($row->fresh()))->resolve($request),
        );
    }

    private function requireStaff(Request $request): User
    {
        $principal = $request->user();

        if (! $principal instanceof User) {
            throw new AuthorizationException('Only staff users may access notifications.');
        }

        return $principal;
    }
}
