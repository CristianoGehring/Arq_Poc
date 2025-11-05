<?php

namespace App\Http\Requests\Charge;

use Illuminate\Foundation\Http\FormRequest;

class UpdateChargeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount' => ['sometimes', 'numeric', 'min:0.01', 'max:999999.99'],
            'description' => ['sometimes', 'string', 'min:3', 'max:500'],
            'due_date' => ['sometimes', 'date', 'after_or_equal:today'],
            'metadata' => ['nullable', 'array'],
        ];
    }

    public function messages(): array
    {
        return [
            'amount.min' => 'Amount must be at least 0.01',
            'description.min' => 'Description must be at least 3 characters',
            'due_date.after_or_equal' => 'Due date must be today or in the future',
        ];
    }
}
