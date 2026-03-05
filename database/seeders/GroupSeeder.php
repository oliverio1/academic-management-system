<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Level;
use App\Models\Modality;
use App\Models\Group;

class GroupSeeder extends Seeder
{
    public function run(): void
    {
        $bacho = Modality::where('name', 'Bachillerato SEP 26-2')->first();

        $terceroSEP = Level::where('name', 'Tercero')->where('modality_id', $bacho->id)->first();
        $cuartoSEP = Level::where('name', 'Cuarto')->where('modality_id', $bacho->id)->first();
        $quintoSEP = Level::where('name', 'Quinto')->where('modality_id', $bacho->id)->first();
        $sextoSEP = Level::where('name', 'Sexto')->where('modality_id', $bacho->id)->first();

        $groupsByLevel = [
            [
                'level' => $terceroSEP,
                'groups' => [
                    ['name' => '3110', 'capacity' => 30, 'modality' => 2, 'sections' => []],
                ]
            ],
            [
                'level' => $cuartoSEP,
                'groups' => [
                    ['name' => '4110', 'capacity' => 30, 'modality' => 2, 'sections' => []],
                ]
            ],
            [
                'level' => $quintoSEP,
                'groups' => [
                    ['name' => '5110', 'capacity' => 30, 'modality' => 2, 'sections' => []],
                    ['name' => '5120', 'capacity' => 30, 'modality' => 2, 'sections' => []],
                ]
            ],
            [
                'level' => $sextoSEP,
                'groups' => [
                    ['name' => '6120', 'capacity' => 30, 'modality' => 2, 'sections' => []],
                ]
            ],
        ];
        foreach($groupsByLevel as $levelData) {
            $level = $levelData['level'];
            foreach($levelData['groups'] as $groupData) {
                $group = Group::create([
                    'level_id' => $level->id,
                    'name' => $groupData['name'],
                    'capacity' => $groupData['capacity'],
                    'is_active' => true
                ]);
            }
        }
    }
}
