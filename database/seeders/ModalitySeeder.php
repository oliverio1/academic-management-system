<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Modality;

class ModalitySeeder extends Seeder
{
    public function run(): void
    {
        $modalities = [
            ['name' => 'Bachillerato SEP 26-2', 'is_active' => 1],
            ['name' => 'PREPARATORIA', 'is_active' => 1],
        ];
        foreach($modalities as $modality) {
            Modality::firstOrCreate(
                ['name' => $modality['name']],
                ['is_active' => $modality['is_active']]
            );
        }
    }
}
