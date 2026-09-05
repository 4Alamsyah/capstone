<?php

namespace App\Support;

use App\Models\User;
use App\Notifications\SystemActivityNotification;

class NotificationDispatcher
{
    /**
     * Notify every active user with at least view access to the given module
     * permission, excluding the user who triggered the event (if any).
     */
    public static function notifyModule(
        string $permissionKey,
        string $type,
        string $title,
        string $description,
        ?string $url = null,
        ?int $excludeUserId = null,
    ): void {
        User::query()
            ->where('is_active', true)
            ->when($excludeUserId, fn ($query) => $query->where('id', '!=', $excludeUserId))
            ->get()
            ->filter(fn (User $user): bool => $user->hasAccess($permissionKey))
            ->each(fn (User $user) => $user->notify(
                new SystemActivityNotification($type, $title, $description, $url)
            ));
    }
}
