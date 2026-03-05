<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            ModalitySeeder::class,
            LevelSeeder::class,
            GroupSeeder::class,
            SubjectSeeder::class,
            TeacherSeeder::class,
            StudentSeeder::class,
            ScheduleSeeder::class,
            AcademicPeriodsSeeder::class,
            AcademicCalendarHolidaySeeder::class,
            AttendanceSeeder::class,
            CourseConfigurationSeeder::class,
            ActivitiesSeeder::class,
            GradesSeeder::class,
            StudentFollowUpSeeder::class,
            AnnouncementSeeder::class,
        ]);
    }
}
