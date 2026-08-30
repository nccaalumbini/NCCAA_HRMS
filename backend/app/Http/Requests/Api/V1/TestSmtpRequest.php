<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class TestSmtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'recipient' => ['required', 'email', 'max:255'],
            'settings' => ['sometimes', 'array'],
            'settings.host' => ['sometimes', 'string'],
            'settings.port' => ['sometimes', 'integer'],
            'settings.encryption' => ['sometimes', 'string'],
            'settings.username' => ['nullable', 'string'],
            'settings.password' => ['nullable', 'string'],
            'settings.from_email' => ['sometimes', 'email'],
            'settings.from_name' => ['nullable', 'string'],
        ];
    }
}
