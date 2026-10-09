<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Exceptions\NotFoundException;
use App\Http\Request;
use App\Http\Response;
use App\Services\Notifier;
use App\Support\Paginator;

class NotificationController
{
    private Notifier $notifier;

    public function __construct(?Notifier $notifier = null)
    {
        $this->notifier = $notifier ?? new Notifier();
    }

    /**
     * GET /api/notifications
     */
    public function index(Request $request): Response
    {
        $userId = (int) $request->getAttribute('auth_user_id');
        $unreadOnly = $request->getQuery('unread') === '1' || $request->getQuery('unread') === 'true';

        $pagination = Paginator::fromRequest($request);
        $total = $this->notifier->countForUser($userId, $unreadOnly);
        $items = $this->notifier->listForUser(
            $userId,
            $unreadOnly,
            $pagination['limit'],
            $pagination['offset']
        );

        $meta = Paginator::buildMeta($total, $pagination['page'], $pagination['per_page']);

        return Response::json([
            'status' => 'ok',
            'data'   => $items,
            'meta'   => $meta,
        ]);
    }

    /**
     * POST /api/notifications/{id}/read
     */
    public function markRead(Request $request, int $id): Response
    {
        $userId = (int) $request->getAttribute('auth_user_id');

        $updated = $this->notifier->markAsRead($userId, $id);

        if (!$updated) {
            // Either already read or does not belong to user - 404 to prevent ID enumeration
            throw new NotFoundException('Notification not found');
        }

        return Response::json([
            'status'  => 'ok',
            'message' => 'Notification marked as read',
        ]);
    }

    /**
     * POST /api/notifications/read-all
     */
    public function markAllRead(Request $request): Response
    {
        $userId = (int) $request->getAttribute('auth_user_id');
        $count = $this->notifier->markAllAsRead($userId);

        return Response::json([
            'status'  => 'ok',
            'message' => "Marked {$count} notification(s) as read",
            'count'   => $count,
        ]);
    }
}
