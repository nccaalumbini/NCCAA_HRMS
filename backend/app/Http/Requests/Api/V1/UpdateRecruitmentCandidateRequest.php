<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRecruitmentCandidateRequest extends FormRequest
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
            'recruitment_status' => ['sometimes', 'string', Rule::in([
                'imported', 'outreach_sent', 'cv_requested', 'cv_submitted', 'rejected', 'converted_to_cadet',
            ])],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }
}
