<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
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
        $userId = $this->route('user')?->id;

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'username' => ['sometimes', 'string', 'regex:/^[a-zA-Z0-9._-]+$/', 'min:3', 'max:50', Rule::unique('users', 'username')->ignore($userId)->withoutTrashed()],
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)->withoutTrashed()],
            'phone' => ['nullable', 'string', 'max:20', Rule::unique('users', 'phone')->ignore($userId)->withoutTrashed()],
            'status' => ['sometimes', 'string', Rule::in(['active', 'disabled'])],
            'cadet_number' => ['nullable', 'string', 'max:50', 'regex:/^[A-Za-z0-9\-\/]+$/', Rule::unique('users', 'cadet_number')->ignore($userId)->withoutTrashed()],
            'rank_id' => ['nullable', 'integer', 'exists:ranks,id'],
            'province_id' => ['nullable', 'integer', 'exists:provinces,id'],
            'district_id' => ['nullable', 'integer', 'exists:districts,id'],
            'local_level' => ['nullable', 'string', 'max:255'],
            'ward_number' => ['nullable', 'integer', 'min:1', 'max:999'],
            'photo' => ['nullable', 'image', 'max:2048'],
        ];
    }
}
