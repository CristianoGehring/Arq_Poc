<?php

namespace App\DTOs\Charge;

readonly class UpdateChargeDTO
{
    public function __construct(
        public ?float $amount = null,
        public ?string $description = null,
        public ?string $dueDate = null,
        public ?array $metadata = null
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            amount: $data['amount'] ?? null,
            description: $data['description'] ?? null,
            dueDate: $data['due_date'] ?? null,
            metadata: $data['metadata'] ?? null
        );
    }

    public function toArray(): array
    {
        return array_filter([
            'amount' => $this->amount,
            'description' => $this->description,
            'due_date' => $this->dueDate,
            'metadata' => $this->metadata,
        ], fn($value) => $value !== null);
    }
}
