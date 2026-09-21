<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class OcrProcessRequest extends FormRequest
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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'feature_type' => 'nullable|in:TEXT_DETECTION,DOCUMENT_TEXT_DETECTION',
            'language_hints' => 'nullable|array|max:10',
            'language_hints.*' => 'string|max:20',
        ];
    }
}
