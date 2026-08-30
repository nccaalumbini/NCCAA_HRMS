<?php

namespace Database\Seeders;

use App\Models\Rank;
use Illuminate\Database\Seeder;

class RankSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $ranks = [
            ['name_en' => 'Senior Under Officer', 'name_ne' => 'वरिष्ठ अन्डर अफिसर', 'short_code' => 'SUO', 'display_order' => 1],
            ['name_en' => 'Company Junior Under Officer', 'name_ne' => 'कम्पनी जुनियर अन्डर अफिसर', 'short_code' => 'CJUO', 'display_order' => 2],
            ['name_en' => 'Platoon Junior Under Officer', 'name_ne' => 'प्लाटुन जुनियर अन्डर अफिसर', 'short_code' => 'PJUO', 'display_order' => 3],
            ['name_en' => 'Quarter Master Sergeant', 'name_ne' => 'हवल्दार मेजर', 'short_code' => 'QMS', 'display_order' => 4],
            ['name_en' => 'Sergeant', 'name_ne' => 'हवल्दार', 'short_code' => 'SGT', 'display_order' => 5],
            ['name_en' => 'Corporal', 'name_ne' => 'नायक', 'short_code' => 'CPL', 'display_order' => 6],
            ['name_en' => 'Lance Corporal', 'name_ne' => 'नायक (ले. ना.)', 'short_code' => 'LCPL', 'display_order' => 7],
            ['name_en' => 'Cadet', 'name_ne' => 'क्याडेट', 'short_code' => 'CDT', 'display_order' => 8],
        ];

        foreach ($ranks as $rank) {
            Rank::updateOrCreate(
                ['short_code' => $rank['short_code']],
                $rank,
            );
        }
    }
}
