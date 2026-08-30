<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
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
        return [
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'regex:/^[a-zA-Z0-9._-]+$/', 'min:3', 'max:50', Rule::unique('users', 'username')->withoutTrashed()],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->withoutTrashed()],
            'phone' => ['nullable', 'string', 'max:20', Rule::unique('users', 'phone')->withoutTrashed()],
            'password' => ['required', 'string', 'min:12', 'confirmed'],
            'status' => ['sometimes', 'string', Rule::in(['active', 'disabled'])],
            'cadet_number' => ['nullable', 'string', 'max:50', 'regex:/^[A-Za-z0-9\-\/]+$/', Rule::unique('users', 'cadet_number')->withoutTrashed()],
            'rank_id' => ['nullable', 'integer', 'exists:ranks,id'],
            'province_id' => ['nullable', 'integer', 'exists:provinces,id'],
            'district_id' => ['nullable', 'integer', 'exists:districts,id'],
            'local_level' => ['nullable', 'string', 'max:255'],
            'ward_number' => ['nullable', 'integer', 'min:1', 'max:999'],
            'photo' => ['nullable', 'image', 'max:2048'],
            'role_ids' => ['sometimes', 'array'],
            'role_ids.*' => ['integer', 'exists:roles,id'],
        ];
    }
}
