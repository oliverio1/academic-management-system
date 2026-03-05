<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Announcement;

class AnnouncementSeeder extends Seeder
{
    public function run()
    {
        Announcement::create([
            'title' => 'Inicio de clases',
            'body' => 'Bienvenidos al nuevo ciclo escolar.',
            'scope' => 'public',
            'audience' => 'all',
            'published_at' => now(),
            'created_by' => 1,
        ]);

        Announcement::create([
            'title' => 'Entrega de calificaciones',
            'body' => 'Recuerden subir calificaciones antes del viernes.',
            'scope' => 'internal',
            'audience' => 'teachers',
            'published_at' => now(),
            'created_by' => 1,
        ]);
    }
}