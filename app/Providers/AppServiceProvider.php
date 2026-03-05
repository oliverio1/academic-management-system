<?php

namespace App\Providers;

use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Carbon\Carbon;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Carbon::setLocale('es');
        View::composer('layouts.app', function ($view) {
            $user = auth()->user();

            if (! $user || ! $user->hasRole('teacher')) {
                return;
            }

            $notifications = $user->unreadNotifications
                ->where('data.type', 'student_follow_up');

            $view->with([
                'followUpNotifications' => $notifications,
                'followUpNotificationsCount' => $notifications->count(),
            ]);
        });
    }
}
