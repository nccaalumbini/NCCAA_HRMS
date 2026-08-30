<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PromoteRecruitmentCandidateRequest extends FormRequest
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
            'cadet_number' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9\-\/]+$/', Rule::unique('users', 'cadet_number')->withoutTrashed(), Rule::unique('cadets', 'cadet_number')],
            'rank_id' => ['required', 'integer', 'exists:ranks,id'],
            'username' => ['required', 'string', 'regex:/^[a-zA-Z0-9._-]+$/', 'min:3', 'max:50', Rule::unique('users', 'username')->withoutTrashed()],
            'password' => ['required', 'string', 'min:12', 'confirmed'],
        ];
    }
}
