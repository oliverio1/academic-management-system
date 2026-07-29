<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class NotificationCenterService
{
    private const CACHE_SECONDS = 20;
    private const RECENT_LIMIT = 8;

    public function navbar(User $user): array
    {
        return Cache::remember(
            $this->cacheKey($user),
            now()->addSeconds(self::CACHE_SECONDS),
            fn () => $this->freshNavbar($user)
        );
    }

    public function forget(User|int $user): void
    {
        $userId = $user instanceof User ? $user->id : $user;
        Cache::forget($this->cacheKey((int) $userId));
    }

    private function freshNavbar(User $user): array
    {
        $notifications = $user->unreadNotifications()
            ->latest()
            ->take(self::RECENT_LIMIT)
            ->get();

        return [
            'unread_count' => $user->unreadNotifications()->count(),
            'items' => $notifications
                ->map(fn (DatabaseNotification $notification) => $this->mapNotification($notification))
                ->values()
                ->all(),
        ];
    }

    private function mapNotification(DatabaseNotification $notification): array
    {
        $data = is_array($notification->data) ? $notification->data : [];
        $title = $data['title']
            ?? $data['student_name']
            ?? 'Notificacion';
        $message = $data['message']
            ?? $data['excerpt']
            ?? 'Tienes una nueva notificacion.';

        return [
            'id' => $notification->id,
            'title' => (string) $title,
            'message' => (string) Str::limit($message, 90),
            'created_at_human' => $notification->created_at?->diffForHumans() ?: '',
            'read_url' => route('notifications.read', $notification),
            'url' => $data['url'] ?? route('dashboard'),
        ];
    }

    private function cacheKey(User|int $user): string
    {
        $userId = $user instanceof User ? $user->id : $user;

        return 'notifications:navbar:user:' . $userId;
    }
}
