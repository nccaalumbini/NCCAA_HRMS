<?php

namespace App\Http\Requests\Api\V1;

use App\Models\RecruitmentCandidate;
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
            'full_name' => ['sometimes', 'string', 'max:255'],
            'gender' => ['sometimes', 'nullable', 'string', Rule::in(['male', 'female', 'other'])],
            'province_id' => ['sometimes', 'nullable', 'integer', 'exists:provinces,id'],
            'district_id' => ['sometimes', 'nullable', 'integer', 'exists:districts,id'],
            'local_level' => ['sometimes', 'nullable', 'string', 'max:255'],
            'ward_number' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:999'],
            'contact_number' => ['sometimes', 'string', 'max:20'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'skills' => ['sometimes', 'nullable', 'array'],
            'skills.*' => ['string', 'max:100'],
            'recruitment_status' => ['sometimes', 'string', Rule::in([
                RecruitmentCandidate::STATUS_IMPORTED,
                RecruitmentCandidate::STATUS_OUTREACH_SENT,
                RecruitmentCandidate::STATUS_CV_REQUESTED,
                RecruitmentCandidate::STATUS_CV_SUBMITTED,
                RecruitmentCandidate::STATUS_REJECTED,
                RecruitmentCandidate::STATUS_CONVERTED,
            ])],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'priority_score' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:100'],
        ];
    }
}
