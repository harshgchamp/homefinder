<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The logged-in admin's database notifications (the "database" channel of
 * NewEnquiryNotification). Notifiable gives User these relations for free:
 *   $user->notifications()        all, newest first
 *   $user->unreadNotifications()  where read_at is null
 */
class NotificationController extends Controller
{
    public function index(Request $request): Response
    {
        $notifications = $this->user($request)->notifications()->paginate(20);

        return Inertia::render('admin/notifications/Index', [
            'notifications' => $notifications->through(fn (DatabaseNotification $notification): array => [
                'id' => $notification->id,
                'type' => $notification->type,
                'data' => $notification->data,
                'read' => $notification->read_at !== null,
                'created_ago' => $notification->created_at?->diffForHumans(),
            ]),
        ]);
    }

    /**
     * Open one notification: mark it read and go to the property it is about.
     */
    public function read(Request $request, string $id): RedirectResponse
    {
        // Looked up through the user's own notifications, so nobody can mark
        // (or open) another admin's notification by guessing its id.
        /** @var DatabaseNotification $notification */
        $notification = $this->user($request)->notifications()->findOrFail($id);
        $notification->markAsRead();

        /** @var array{property_id?: int} $data */
        $data = $notification->data;

        return isset($data['property_id'])
            ? to_route('admin.properties.show', $data['property_id'])
            : to_route('admin.notifications.index');
    }

    public function readAll(Request $request): RedirectResponse
    {
        // One UPDATE for all unread rows (a mass update; no events needed here).
        $this->user($request)->unreadNotifications()->update(['read_at' => now()]);

        return back();
    }

    private function user(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}
