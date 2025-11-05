<?php

namespace App\Http\Requests\Customer;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:3', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'document' => ['required', 'string', 'size:11'], // CPF
            'phone' => ['nullable', 'string', 'min:10', 'max:15'],
            'address' => ['nullable', 'array'],
            'address.street' => ['required_with:address', 'string'],
            'address.number' => ['required_with:address', 'string'],
            'address.city' => ['required_with:address', 'string'],
            'address.state' => ['required_with:address', 'string', 'size:2'],
            'address.zip_code' => ['required_with:address', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Customer name is required',
            'name.min' => 'Customer name must be at least 3 characters',
            'email.required' => 'Email is required',
            'email.email' => 'Email must be a valid email address',
            'document.required' => 'Document (CPF) is required',
            'document.size' => 'Document must be exactly 11 digits',
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
