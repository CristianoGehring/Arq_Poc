<?php

declare(strict_types=1);

namespace App\Http\Requests\Customer;

use Illuminate\Foundation\Http\FormRequest;

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
            'email' => ['required', 'email', 'max:255', 'unique:customers,email'],
            'document' => ['required', 'string', 'max:20', 'unique:customers,document'],
            'phone' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'array'],
            'address.street' => ['required_with:address', 'string', 'max:255'],
            'address.number' => ['required_with:address', 'string', 'max:20'],
            'address.complement' => ['nullable', 'string', 'max:255'],
            'address.neighborhood' => ['nullable', 'string', 'max:100'],
            'address.city' => ['required_with:address', 'string', 'max:100'],
            'address.state' => ['required_with:address', 'string', 'size:2'],
            'address.zip_code' => ['required_with:address', 'string', 'max:10'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'O nome é obrigatório',
            'name.min' => 'O nome deve ter no mínimo 3 caracteres',
            'email.required' => 'O email é obrigatório',
            'email.email' => 'O email deve ser válido',
            'email.unique' => 'Este email já está cadastrado',
            'document.required' => 'O documento é obrigatório',
            'document.unique' => 'Este documento já está cadastrado',
            'address.street.required_with' => 'A rua é obrigatória quando o endereço é informado',
            'address.number.required_with' => 'O número é obrigatório quando o endereço é informado',
            'address.city.required_with' => 'A cidade é obrigatória quando o endereço é informado',
            'address.state.required_with' => 'O estado é obrigatório quando o endereço é informado',
            'address.state.size' => 'O estado deve ter 2 caracteres',
            'address.zip_code.required_with' => 'O CEP é obrigatório quando o endereço é informado',
        ];
    }
}
