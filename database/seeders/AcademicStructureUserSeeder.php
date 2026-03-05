<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Modality;
use App\Models\Level;
use App\Models\Group;

class AcademicStructureUserSeeder extends Seeder
{
    public function run(): void
    {
        $modality = Modality::firstOrCreate([
            'name' => 'Bachillerato'
        ]);
        $level = Level::firstOrCreate([
            'modality_id' => $modality->id,
            'name' => 'Primer Semestre'
        ], [
            'order' => 1
        ]);
        Group::firstOrCreate([
            'level_id' => $level->id,
            'name' => 'Grupo A'
        ], [
            'capacity' => 40
        ]);
    }
}
