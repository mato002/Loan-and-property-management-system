<?php

namespace App\Http\Controllers\Loan;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class LoanNotificationController extends Controller
{
    public function index(Request $request): View
    {
        $notifications = collect();
        $unreadCount = 0;

        if (Schema::hasTable('notifications')) {
            $notifications = $request->user()
                ?->notifications()
                ->latest()
                ->paginate(30)
                ->withQueryString();

            $unreadCount = (int) ($request->user()?->unreadNotifications()->count() ?? 0);
        }

        return view('loan.notifications.index', [
            'notifications' => $notifications,
            'unreadCount' => $unreadCount,
        ]);
    }

    public function readAll(Request $request): RedirectResponse
    {
        if (Schema::hasTable('notifications')) {
            $request->user()?->unreadNotifications->markAsRead();
        }

        return back()->with('status', 'All notifications marked as read.');
    }

    public function readOne(Request $request, string $notification): RedirectResponse
    {
        if (Schema::hasTable('notifications')) {
            $item = $request->user()?->notifications()->where('id', $notification)->first();
            if ($item && $item->read_at === null) {
                $item->markAsRead();
            }
        }

        return back()->with('status', 'Notification marked as read.');
    }
}

