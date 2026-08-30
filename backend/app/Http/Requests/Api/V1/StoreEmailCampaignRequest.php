<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreEmailCampaignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'subject' => ['required', 'string', 'max:255'],
            'body_html' => ['required', 'string', 'max:50000'],
            'sender_name' => ['nullable', 'string', 'max:255'],
            'sender_email' => ['nullable', 'email', 'max:255'],
            'reply_to' => ['nullable', 'email', 'max:255'],
            'cadet_ids' => ['sometimes', 'array'],
            'cadet_ids.*' => ['integer', 'exists:cadets,id'],
            'manual_emails' => ['sometimes', 'array'],
            'manual_emails.*' => ['email', 'max:255'],
        ];
    }
}
