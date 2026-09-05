<?php

namespace Database\Seeders;

use App\Models\NccTrainingCenter;
use Illuminate\Database\Seeder;

class NccTrainingCenterSeeder extends Seeder
{
    /**
     * Seed the fixed NCC passout training centers (each row is a real academy).
     */
    public function run(): void
    {
        $centers = [
            [
                'slug' => 'eastern_yangshila',
                'name_en' => 'Eastern Training Academy — Yangshila',
                'name_ne' => 'पूर्वी तालिम शिक्षालय (Eastern) — याङशिला',
            ],
            [
                'slug' => 'mid_eastern_bardibas',
                'name_en' => 'Mid Eastern Training Academy — Bardibas',
                'name_ne' => 'मध्य पूर्वी तालिम शिक्षालय (Mid Eastern) — Bardibas',
            ],
            [
                'slug' => 'mid_hattikhor',
                'name_en' => 'Mid Training Academy — Hattikhor',
                'name_ne' => 'मध्य तालिम शिक्षालय (Mid) — हात्तीखोर',
            ],
            [
                'slug' => 'western_kohalpur',
                'name_en' => 'Western Training Academy — Kohalpur',
                'name_ne' => 'पश्चिम तालिम शिक्षालय (Western) — कोहलपुर',
            ],
        ];

        foreach ($centers as $index => $center) {
            NccTrainingCenter::updateOrCreate(
                ['slug' => $center['slug']],
                [...$center, 'sort_order' => $index],
            );
        }
    }
}
