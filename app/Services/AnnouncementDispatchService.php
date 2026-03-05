<?php

namespace App\Services;

class AnnouncementDispatchService
{
    public function dispatch(Announcement $announcement, array $data = [])
    {
        if ($announcement->scope !== 'internal') {
            return;
        }

        $users = $this->resolveUsers($announcement, $data);

        foreach ($users as $user) {
            $announcement->recipients()->create([
                'user_id' => $user->id,
            ]);
        }
    }

    protected function resolveUsers(Announcement $announcement, array $data)
    {
        return match ($announcement->audience) {
            'all'      => User::all(),
            'teachers' => User::whereHas('teacher')->get(),
            'students' => User::whereHas('student')->get(),
            'specific' => User::whereIn('id', $data['user_ids'])->get(),
        };
    }
}