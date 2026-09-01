<?php

namespace App\Http\Requests\Api\V1;

use App\Models\RecruitmentCandidate;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRecruitmentCandidateRequest extends FormRequest
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:255'],
            'gender' => ['nullable', 'string', Rule::in(['male', 'female', 'other'])],
            'province_id' => ['nullable', 'integer', 'exists:provinces,id'],
            'district_id' => ['nullable', 'integer', 'exists:districts,id'],
            'local_level' => ['nullable', 'string', 'max:255'],
            'ward_number' => ['nullable', 'integer', 'min:1', 'max:999'],
            'contact_number' => ['required', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'skills' => ['nullable', 'array'],
            'skills.*' => ['string', 'max:100'],
            'recruitment_status' => ['sometimes', 'string', Rule::in([
                RecruitmentCandidate::STATUS_IMPORTED,
                RecruitmentCandidate::STATUS_OUTREACH_SENT,
                RecruitmentCandidate::STATUS_CV_REQUESTED,
                RecruitmentCandidate::STATUS_CV_SUBMITTED,
                RecruitmentCandidate::STATUS_REJECTED,
                RecruitmentCandidate::STATUS_CONVERTED,
            ])],
            'notes' => ['nullable', 'string', 'max:2000'],
            'priority_score' => ['nullable', 'integer', 'min:0', 'max:100'],
        ];
    }
}
