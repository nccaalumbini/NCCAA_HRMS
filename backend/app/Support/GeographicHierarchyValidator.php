<?php

namespace App\Support;

use App\Models\District;
use App\Models\LocalLevel;
use App\Models\Ward;
use Illuminate\Validation\ValidationException;

class GeographicHierarchyValidator
{
    /**
     * Validate consistency of province, district, local level, and ward hierarchy.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public static function validate(array $data): void
    {
        $provinceId = isset($data['province_id']) && $data['province_id'] !== '' ? (int) $data['province_id'] : null;
        $districtId = isset($data['district_id']) && $data['district_id'] !== '' ? (int) $data['district_id'] : null;
        $localLevelId = isset($data['local_level_id']) && $data['local_level_id'] !== '' ? (int) $data['local_level_id'] : null;
        $wardId = isset($data['ward_id']) && $data['ward_id'] !== '' ? (int) $data['ward_id'] : null;

        if ($provinceId !== null && $districtId !== null) {
            $valid = District::where('id', $districtId)->where('province_id', $provinceId)->exists();
            if (! $valid) {
                throw ValidationException::withMessages([
                    'district_id' => ['The selected district does not belong to the selected province.'],
                ]);
            }
        }

        if ($districtId !== null && $localLevelId !== null) {
            $valid = LocalLevel::where('id', $localLevelId)->where('district_id', $districtId)->exists();
            if (! $valid) {
                throw ValidationException::withMessages([
                    'local_level_id' => ['The selected local level does not belong to the selected district.'],
                ]);
            }
        }

        if ($localLevelId !== null && $wardId !== null) {
            $valid = Ward::where('id', $wardId)->where('local_level_id', $localLevelId)->exists();
            if (! $valid) {
                throw ValidationException::withMessages([
                    'ward_id' => ['The selected ward does not belong to the selected local level.'],
                ]);
            }
        }
    }
}
