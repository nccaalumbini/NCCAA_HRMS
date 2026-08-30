<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCadetRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $cadetId = $this->route('cadet')?->id;

        return [
            'cadet_number' => ['sometimes', 'string', 'max:50', Rule::unique('cadets', 'cadet_number')->ignore($cadetId)->withoutTrashed()],
            'name' => ['sometimes', 'string', 'max:255'],
            'rank_id' => ['nullable', 'integer', 'exists:ranks,id'],
            'province_id' => ['nullable', 'integer', 'exists:provinces,id'],
            'district_id' => ['nullable', 'integer', 'exists:districts,id'],
            'local_level_id' => ['nullable', 'integer', 'exists:local_levels,id'],
            'ward_id' => ['nullable', 'integer', 'exists:wards,id'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'status' => ['sometimes', 'string', Rule::in(['active', 'inactive', 'suspended', 'graduated'])],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],

            'profile' => ['sometimes', 'array'],
            'profile.date_of_birth' => ['nullable', 'date', 'before:today'],
            'profile.gender' => ['nullable', 'string', Rule::in(['male', 'female', 'other'])],
            'profile.blood_group' => ['nullable', 'string', Rule::in(['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'])],
            'profile.photo_path' => ['nullable', 'string', 'max:255'],
            'profile.father_name' => ['nullable', 'string', 'max:255'],
            'profile.mother_name' => ['nullable', 'string', 'max:255'],
            'profile.guardian_name' => ['nullable', 'string', 'max:255'],
            'profile.guardian_phone' => ['nullable', 'string', 'max:20'],
            'profile.guardian_relation' => ['nullable', 'string', 'max:50'],
            'profile.citizenship_number' => ['nullable', 'string', 'max:50'],
            'profile.local_address' => ['nullable', 'string', 'max:255'],
            'profile.enrollment_date' => ['nullable', 'date'],
        ];
    }
}
