<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/**
 * PLACEHOLDER API Controller per Notifications
 * TODO: Implementare completamente quando necessario
 */
class NotificationApiController extends Controller
{
    public function emailWebhook(Request $request)
    {
        // TODO: Implementare webhook per email
        return response()->json(['message' => 'Not implemented yet'], 501);
    }

    public function smsWebhook(Request $request)
    {
        // TODO: Implementare webhook per SMS
        return response()->json(['message' => 'Not implemented yet'], 501);
    }

    public function subscribe(Request $request)
    {
        // TODO: Implementare subscribe
        return response()->json(['message' => 'Not implemented yet'], 501);
    }

    public function unsubscribe(Request $request)
    {
        // TODO: Implementare unsubscribe
        return response()->json(['message' => 'Not implemented yet'], 501);
    }

    public function myNotifications()
    {
        // TODO: Implementare my notifications
        return response()->json(['message' => 'Not implemented yet'], 501);
    }

    public function markRead($notification)
    {
        // TODO: Implementare mark read
        return response()->json(['message' => 'Not implemented yet'], 501);
    }

    public function markAllRead()
    {
        // TODO: Implementare mark all read
        return response()->json(['message' => 'Not implemented yet'], 501);
    }

    public function send(Request $request)
    {
        // TODO: Implementare send
        return response()->json(['message' => 'Not implemented yet'], 501);
    }

    public function deliveryStatus($notification)
    {
        // TODO: Implementare delivery status
        return response()->json(['message' => 'Not implemented yet'], 501);
    }

    public function bulkSend(Request $request)
    {
        // TODO: Implementare bulk send
        return response()->json(['message' => 'Not implemented yet'], 501);
    }
}
