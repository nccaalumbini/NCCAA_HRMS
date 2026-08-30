<?php

namespace Database\Seeders;

use App\Models\District;
use App\Models\Province;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class GeographySeeder extends Seeder
{
    /**
     * The seven provinces and their districts.
     *
     * @var array<int, array{name_en: string, code: string, districts: array<int, string>}>
     */
    private array $provinces = [
        [
            'name_en' => 'Koshi', 'code' => 'P1',
            'districts' => [
                'Taplejung', 'Sankhuwasabha', 'Solukhumbu', 'Okhaldhunga', 'Khotang',
                'Bhojpur', 'Dhankuta', 'Terhathum', 'Panchthar', 'Ilam',
                'Jhapa', 'Morang', 'Sunsari', 'Udayapur',
            ],
        ],
        [
            'name_en' => 'Madhesh', 'code' => 'P2',
            'districts' => [
                'Saptari', 'Siraha', 'Dhanusa', 'Mahottari', 'Sarlahi',
                'Rautahat', 'Bara', 'Parsa',
            ],
        ],
        [
            'name_en' => 'Bagmati', 'code' => 'P3',
            'districts' => [
                'Dolakha', 'Sindhupalchok', 'Rasuwa', 'Dhading', 'Nuwakot',
                'Kathmandu', 'Bhaktapur', 'Lalitpur', 'Kavrepalanchok', 'Sindhuli',
                'Ramechhap', 'Makwanpur', 'Chitwan',
            ],
        ],
        [
            'name_en' => 'Gandaki', 'code' => 'P4',
            'districts' => [
                'Gorkha', 'Lamjung', 'Tanahun', 'Syangja', 'Kaski',
                'Manang', 'Mustang', 'Parbat', 'Myagdi', 'Baglung', 'Nawalparasi East',
            ],
        ],
        [
            'name_en' => 'Lumbini', 'code' => 'P5',
            'districts' => [
                'Rukum East', 'Rolpa', 'Pyuthan', 'Gulmi', 'Arghakhanchi',
                'Palpa', 'Rupandehi', 'Kapilvastu', 'Nawalparasi West', 'Dang',
                'Banke', 'Bardiya',
            ],
        ],
        [
            'name_en' => 'Karnali', 'code' => 'P6',
            'districts' => [
                'Dolpa', 'Jumla', 'Kalikot', 'Mugu', 'Humla',
                'Jajarkot', 'Dailekh', 'Salyan', 'Surkhet', 'Rukum West',
            ],
        ],
        [
            'name_en' => 'Sudurpashchim', 'code' => 'P7',
            'districts' => [
                'Bajura', 'Bajhang', 'Darchula', 'Baitadi', 'Doti',
                'Achham', 'Kailali', 'Kanchanpur', 'Dadeldhura',
            ],
        ],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach ($this->provinces as $provinceData) {
            $province = Province::updateOrCreate(
                ['code' => $provinceData['code']],
                ['name_en' => $provinceData['name_en']],
            );

            foreach ($provinceData['districts'] as $districtName) {
                District::updateOrCreate(
                    ['province_id' => $province->id, 'code' => Str::upper(Str::slug($districtName, '_'))],
                    ['name_en' => $districtName],
                );
            }
        }
    }
}
