<?php

namespace Database\Seeders;

use App\Models\Cadet;
use App\Models\District;
use App\Models\Province;
use App\Models\Rank;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class E2ETestSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            PermissionSeeder::class,
            RoleSeeder::class,
        ]);

        $superAdminRole = Role::firstOrCreate(['slug' => Role::SUPER_ADMIN], ['name' => 'Super Admin']);

        $admin = User::firstOrCreate(
            ['username' => 'admin'],
            [
                'name' => 'Super Administrator',
                'email' => 'admin@nccaa.local',
                'password' => Hash::make('password123'),
                'status' => 'active',
            ]
        );
        $admin->roles()->syncWithoutDetaching([$superAdminRole->id]);

        $bagmati = Province::firstOrCreate(['code' => 'P3'], ['name_en' => 'Bagmati', 'name_ne' => 'बागमती']);
        $ktm = District::firstOrCreate(['code' => 'KTM'], ['name_en' => 'Kathmandu', 'name_ne' => 'काठमाडौं', 'province_id' => $bagmati->id]);
        $lal = District::firstOrCreate(['code' => 'LAL'], ['name_en' => 'Lalitpur', 'name_ne' => 'ललितपुर', 'province_id' => $bagmati->id]);

        $rankSgt = Rank::firstOrCreate(['short_code' => 'C/SGT'], ['name_en' => 'Cadet Sergeant', 'display_order' => 3]);
        $rankCpl = Rank::firstOrCreate(['short_code' => 'C/CPL'], ['name_en' => 'Cadet Corporal', 'display_order' => 2]);
        $rankCdt = Rank::firstOrCreate(['short_code' => 'CDT'], ['name_en' => 'Cadet', 'display_order' => 1]);

        $cadets = [
            ['cadet_number' => 'NCC-E2E-001', 'name' => 'Aarav Sharma', 'email' => 'aarav.sharma@example.com', 'phone' => '9841000001', 'rank_id' => $rankSgt->id, 'province_id' => $bagmati->id, 'district_id' => $ktm->id],
            ['cadet_number' => 'NCC-E2E-002', 'name' => 'Bhawana Karki', 'email' => 'bhawana.karki@example.com', 'phone' => '9841000002', 'rank_id' => $rankCpl->id, 'province_id' => $bagmati->id, 'district_id' => $ktm->id],
            ['cadet_number' => 'NCC-E2E-003', 'name' => 'Chetan Shrestha', 'email' => 'chetan.shrestha@example.com', 'phone' => '9841000003', 'rank_id' => $rankCdt->id, 'province_id' => $bagmati->id, 'district_id' => $lal->id],
        ];

        foreach ($cadets as $c) {
            Cadet::updateOrCreate(
                ['cadet_number' => $c['cadet_number']],
                [
                    'name' => $c['name'],
                    'email' => $c['email'],
                    'phone' => $c['phone'],
                    'rank_id' => $c['rank_id'],
                    'province_id' => $c['province_id'],
                    'district_id' => $c['district_id'],
                    'status' => 'active',
                ]
            );
        }
    }
}
