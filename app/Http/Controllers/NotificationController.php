<?php

namespace App\Http\Controllers;

use App\Services\NotificationService;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function read(Request $request, int $recipient)
    {
        NotificationService::markRead($request->user(), $recipient);

        return response()->json(['ok' => true]);
    }

    public function readAll(Request $request)
    {
        NotificationService::markAllRead($request->user());

        if ($request->expectsJson()) {
            return response()->json(['ok' => true]);
        }

        return back()->with('success', 'All notifications were marked as read.');
    }

    public function dismiss(Request $request, int $recipient)
    {
        NotificationService::dismiss($request->user(), $recipient);

        if ($request->expectsJson()) {
            return response()->json(['ok' => true]);
        }

        return back()->with('success', 'Notification removed.');
    }

    public function clear(Request $request)
    {
        NotificationService::dismissAll($request->user());

        if ($request->expectsJson()) {
            return response()->json(['ok' => true]);
        }

        return back()->with('success', 'All notifications were cleared.');
    }
}
