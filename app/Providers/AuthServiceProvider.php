<?php

namespace App\Providers;

use App\Models\Student;
use App\Models\Teacher;
use App\Policies\StudentPolicy;
use App\Policies\TeacherPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        Student::class => StudentPolicy::class,
        Teacher::class => TeacherPolicy::class,
    ];

    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->registerPolicies();
    }
}