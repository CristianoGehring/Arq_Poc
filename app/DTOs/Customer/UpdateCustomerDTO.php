<?php

declare(strict_types=1);

namespace App\DTOs\Customer;

use App\Enums\CustomerStatus;

readonly class UpdateCustomerDTO
{
    public function __construct(
        public ?string $name = null,
        public ?string $email = null,
        public ?string $phone = null,
        public ?array $address = null,
        public ?CustomerStatus $status = null,
    ) {
    }

    public static function fromRequest(array $data): self
    {
        return new self(
            name: $data['name'] ?? null,
            email: $data['email'] ?? null,
            phone: $data['phone'] ?? null,
            address: $data['address'] ?? null,
            status: isset($data['status']) ? CustomerStatus::from($data['status']) : null,
        );
    }

    public function toArray(): array
    {
        $data = [];

        if ($this->name !== null) {
            $data['name'] = $this->name;
        }

        if ($this->email !== null) {
            $data['email'] = $this->email;
        }

        if ($this->phone !== null) {
            $data['phone'] = $this->phone;
        }

        if ($this->address !== null) {
            $data['address'] = $this->address;
        }

        if ($this->status !== null) {
            $data['status'] = $this->status;
        }

        return $data;
    }

    public function hasChanges(): bool
    {
        return $this->name !== null
            || $this->email !== null
            || $this->phone !== null
            || $this->address !== null
            || $this->status !== null;
    }
}
