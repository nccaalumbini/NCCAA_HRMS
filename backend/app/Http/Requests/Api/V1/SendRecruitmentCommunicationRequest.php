<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SendRecruitmentCommunicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'channel' => ['required', Rule::in(['email', 'whatsapp'])],
            'subject' => ['required_if:channel,email', 'nullable', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:10000'],
        ];
    }
}
