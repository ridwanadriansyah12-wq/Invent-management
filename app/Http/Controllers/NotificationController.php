<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index()
    {
        $role  = auth()->user()->role;
        $notifs = Notification::forRole($role)
            ->with('item')
            ->orderByDesc('created_at')
            ->paginate(30);

        return view('notifications.index', compact('notifs'));
    }

    public function markRead(Notification $notification)
    {
        $notification->update(['is_read' => true]);
        return back()->with('success', 'Notifikasi telah ditandai dibaca.');
    }

    public function markAllRead()
    {
        $role = auth()->user()->role;
        Notification::forRole($role)->unread()->update(['is_read' => true]);
        return back()->with('success', 'Semua notifikasi telah ditandai dibaca.');
    }

    public function destroy(Notification $notification)
    {
        $notification->delete();
        return back()->with('success', 'Notifikasi dihapus.');
    }

    /**
     * API endpoint: get unread count for topbar badge (called via fetch).
     */
    public function unreadCount()
    {
        $role  = auth()->user()->role;
        $count = Notification::forRole($role)->unread()->count();
        return response()->json(['count' => $count]);
    }
}
