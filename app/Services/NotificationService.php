<?php

namespace App\Services;

use App\Models\AppNotification;
use App\Models\User;

/**
 * Creates in-app notifications (Notifications screen). Push delivery (FCM/APNs) can be added here later.
 */
class NotificationService
{
    public function send(User $user, string $type, string $category, string $title, ?string $body = null, array $data = []): ?AppNotification
    {
        if ($category === 'messages' && ! $user->notify_messages) {
            return null;
        }
        return AppNotification::create([
            'user_id' => $user->id,
            'type' => $type,
            'category' => $category,
            'title' => $title,
            'body' => $body,
            'data' => $data,
        ]);
    }
}
