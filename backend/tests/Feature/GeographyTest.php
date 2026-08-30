<?php

namespace Tests\Feature;

use App\Models\District;
use App\Models\LocalLevel;
use App\Models\Province;
use App\Models\Ward;
use Database\Seeders\GeographySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GeographyTest extends TestCase
{
    use RefreshDatabase;

    public function test_geography_seeder_creates_all_seven_provinces_and_77_districts(): void
    {
        $this->seed(GeographySeeder::class);

        $this->assertDatabaseCount('provinces', 7);
        $this->assertDatabaseCount('districts', 77);
    }

    public function test_district_belongs_to_correct_province(): void
    {
        $this->seed(GeographySeeder::class);

        $kathmandu = District::where('name_en', 'Kathmandu')->first();
        $this->assertEquals('Bagmati', $kathmandu->province->name_en);

        $dadeldhura = District::where('name_en', 'Dadeldhura')->first();
        $this->assertEquals('Sudurpashchim', $dadeldhura->province->name_en);
    }

    public function test_province_has_districts(): void
    {
        $this->seed(GeographySeeder::class);

        $koshi = Province::where('code', 'P1')->first();
        $this->assertCount(14, $koshi->districts);
    }

    public function test_district_and_ward_relationships(): void
    {
        $province = Province::factory()->create(['code' => 'P9']);
        $district = District::factory()->create(['province_id' => $province->id]);
        $localLevel = LocalLevel::factory()->create(['district_id' => $district->id]);
        $ward = Ward::factory()->create(['local_level_id' => $localLevel->id]);

        $this->assertTrue($district->province->is($province));
        $this->assertTrue($localLevel->district->is($district));
        $this->assertTrue($ward->localLevel->is($localLevel));
        $this->assertTrue($localLevel->wards->contains($ward));
    }
}
