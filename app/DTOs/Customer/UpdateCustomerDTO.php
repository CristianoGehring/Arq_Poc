<?php

namespace App\DTOs\Customer;

use App\Enums\CustomerStatus;

readonly class UpdateCustomerDTO
{
    public function __construct(
        public ?string $name = null,
        public ?string $email = null,
        public ?string $phone = null,
        public ?array $address = null,
        public ?CustomerStatus $status = null
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            name: $data['name'] ?? null,
            email: $data['email'] ?? null,
            phone: $data['phone'] ?? null,
            address: $data['address'] ?? null,
            status: isset($data['status'])
                ? CustomerStatus::from($data['status'])
                : null
        );
    }

    public function toArray(): array
    {
        return array_filter([
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'address' => $this->address,
            'status' => $this->status?->value,
        ], fn($value) => $value !== null);
    }
}
