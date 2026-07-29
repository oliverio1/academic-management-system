<?php

namespace App\Providers;

use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Carbon\Carbon;
use App\Services\NotificationCenterService;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\Event;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Carbon::setLocale('es');
        Event::listen(NotificationSent::class, function (NotificationSent $event) {
            if ($event->channel === 'database' && isset($event->notifiable->id)) {
                app(NotificationCenterService::class)->forget((int) $event->notifiable->id);
            }
        });

        View::composer('layouts.app', function ($view) {
            $user = auth()->user();

            if (! $user || ! $user->hasRole('teacher')) {
                return;
            }

            $notifications = $user->unreadNotifications()
                ->where('data->type', 'student_follow_up');

            $view->with([
                'followUpNotifications' => $notifications->latest()->take(6)->get(),
                'followUpNotificationsCount' => (clone $notifications)->count(),
            ]);
        });
    }
}
