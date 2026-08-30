<?php

namespace Tests\Feature;

use App\Models\Rank;
use Database\Seeders\RankSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RankTest extends TestCase
{
    use RefreshDatabase;

    public function test_rank_seeder_creates_all_eight_ranks(): void
    {
        $this->seed(RankSeeder::class);

        $this->assertDatabaseCount('ranks', 8);

        foreach (['SUO', 'CJUO', 'PJUO', 'QMS', 'SGT', 'CPL', 'LCPL', 'CDT'] as $code) {
            $this->assertDatabaseHas('ranks', ['short_code' => $code]);
        }
    }

    public function test_ranks_are_seeded_in_display_order(): void
    {
        $this->seed(RankSeeder::class);

        $ordered = Rank::orderBy('display_order')->pluck('short_code')->all();

        $this->assertSame(['SUO', 'CJUO', 'PJUO', 'QMS', 'SGT', 'CPL', 'LCPL', 'CDT'], $ordered);
    }

    public function test_rank_short_code_is_unique(): void
    {
        $this->seed(RankSeeder::class);

        $this->expectException(QueryException::class);

        Rank::create([
            'name_en' => 'Duplicate',
            'short_code' => 'SGT',
            'display_order' => 99,
        ]);
    }
}
