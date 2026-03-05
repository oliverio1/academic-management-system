<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Modality;
use App\Models\Level;

class LevelSeeder extends Seeder
{
    public function run(): void
    {
        $bacho = Modality::where('name', 'Bachillerato SEP 26-2')->first();

        $levels = [
            ['modality_id' => $bacho->id, 'name' => 'Tercero', 'is_active' => 1],
            ['modality_id' => $bacho->id, 'name' => 'Cuarto', 'is_active' => 1],
            ['modality_id' => $bacho->id, 'name' => 'Quinto', 'is_active' => 1],
            ['modality_id' => $bacho->id, 'name' => 'Sexto', 'is_active' => 1],
        ];
        foreach($levels as $level) {
            Level::create([
                'modality_id' => $level['modality_id'],
                'name' => $level['name'],
                'is_active' => $level['is_active']
            ]);
        }
    }
}
