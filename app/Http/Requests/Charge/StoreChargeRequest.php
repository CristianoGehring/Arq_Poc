<?php

namespace App\Http\Requests\Charge;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreChargeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999.99'],
            'description' => ['required', 'string', 'min:3', 'max:500'],
            'payment_method' => ['required', 'string', 'in:credit_card,debit_card,boleto,pix'],
            'due_date' => ['required', 'date', 'after_or_equal:today'],
            'payment_gateway_id' => ['nullable', 'integer', 'exists:payment_gateways,id'],
            'metadata' => ['nullable', 'array'],
        ];
    }

    public function messages(): array
    {
        return [
            'customer_id.required' => 'Customer is required',
            'customer_id.exists' => 'Customer not found',
            'amount.required' => 'Amount is required',
            'amount.min' => 'Amount must be at least 0.01',
            'description.required' => 'Description is required',
            'description.min' => 'Description must be at least 3 characters',
            'payment_method.required' => 'Payment method is required',
            'payment_method.in' => 'Invalid payment method',
            'due_date.required' => 'Due date is required',
            'due_date.after_or_equal' => 'Due date must be today or in the future',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422)
        );
    }
}
