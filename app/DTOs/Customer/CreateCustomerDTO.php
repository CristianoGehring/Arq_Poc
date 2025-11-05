<?php

namespace App\DTOs\Customer;

use App\Enums\CustomerStatus;

readonly class CreateCustomerDTO
{
    public function __construct(
        public string $name,
        public string $email,
        public string $document,
        public ?string $phone = null,
        public ?array $address = null
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            name: $data['name'],
            email: $data['email'],
            document: $data['document'],
            phone: $data['phone'] ?? null,
            address: $data['address'] ?? null
        );
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'email' => $this->email,
            'document' => $this->document,
            'phone' => $this->phone,
            'address' => $this->address,
            'status' => CustomerStatus::ACTIVE->value,
        ];
    }
}
